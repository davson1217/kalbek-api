<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\User;
use Database\Seeders\CharacterSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_user_can_register_with_a_profile_and_api_token(): void
    {
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
