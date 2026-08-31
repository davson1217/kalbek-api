<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\User;
use App\Notifications\ResetPasswordForFrontend;
use App\Notifications\WelcomeToKalbek;
use Database\Seeders\CharacterSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_user_can_register_with_a_profile_and_api_token(): void
    {
        Notification::fake();
        $this->seed(CharacterSeeder::class);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('user.name', 'Ada Lovelace')
            ->assertJsonPath('user.profile.display_name', 'Ada Lovelace')
            ->assertJsonPath('user.profile.avatar_character.id', 'gabija')
            ->assertJsonStructure(['token']);

        $this->assertDatabaseHas('users', ['email' => 'ada@example.com']);
        $this->assertDatabaseHas('personal_access_tokens', ['name' => 'learner']);
        Notification::assertSentTo(User::query()->where('email', 'ada@example.com')->first(), WelcomeToKalbek::class);
    }

    public function test_user_can_request_a_password_reset_link(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'ada@example.com']);

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'ada@example.com',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'If that email exists, we sent password reset instructions.');

        Notification::assertSentTo($user, ResetPasswordForFrontend::class, function (ResetPasswordForFrontend $notification) use ($user): bool {
            $mail = $notification->toMail($user);

            return str_contains($mail->actionUrl, '/auth?')
                && str_contains($mail->actionUrl, 'mode=reset')
                && str_contains($mail->actionUrl, 'email=ada%40example.com');
        });
    }

    public function test_user_can_reset_their_password_with_a_valid_token(): void
    {
        $user = User::factory()->create([
            'email' => 'ada@example.com',
            'password' => 'old-password',
        ]);
        $token = Password::broker()->createToken($user);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'ada@example.com',
            'token' => $token,
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Your password has been reset. You can now sign in.');

        $this->assertTrue(Hash::check('new-password123', $user->fresh()->password));
    }

    public function test_user_can_login_and_fetch_the_authenticated_user(): void
    {
        $this->seed(CharacterSeeder::class);
        $character = Character::query()->where('slug', 'gabija')->firstOrFail();
        $user = User::factory()->create([
            'email' => 'ada@example.com',
            'password' => 'password123',
        ]);
        $user->profile()->create([
            'avatar_character_id' => $character->id,
            'display_name' => 'Ada',
        ]);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'ada@example.com',
            'password' => 'password123',
        ]);

        $token = $login
            ->assertOk()
            ->assertJsonPath('user.profile.display_name', 'Ada')
            ->json('token');

        $this->withToken($token)
            ->getJson('/api/v1/auth/user')
            ->assertOk()
            ->assertJsonPath('user.email', 'ada@example.com')
            ->assertJsonPath('user.profile.avatar_character.id', 'gabija');
    }

    public function test_invalid_login_credentials_return_validation_error(): void
    {
        User::factory()->create([
            'email' => 'ada@example.com',
            'password' => 'password123',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'ada@example.com',
            'password' => 'wrong-password',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_user_can_logout_the_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('learner')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Logged out.');

        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $user->id]);
    }

    public function test_profile_requires_authentication(): void
    {
        $this->getJson('/api/v1/profile')->assertUnauthorized();
    }
}
