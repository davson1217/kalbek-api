<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Auth\ResolveGoogleUser;
use Database\Seeders\CharacterSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class GoogleOAuthApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_google_oauth_can_create_a_laravel_user_and_profile(): void
    {
        $this->seed(CharacterSeeder::class);

        $user = app(ResolveGoogleUser::class)->handle(SocialiteUser::fake([
            'id' => 'google-123',
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'avatar' => 'https://example.com/ada.png',
        ]));

        $this->assertSame('ada@example.com', $user->email);
        $this->assertSame('google-123', $user->google_id);
        $this->assertSame('https://example.com/ada.png', $user->avatar_url);
        $this->assertDatabaseHas('profiles', [
            'user_id' => $user->id,
            'display_name' => 'Ada Lovelace',
        ]);
    }

    public function test_google_oauth_links_an_existing_email_user(): void
    {
        $existing = User::factory()->create([
            'email' => 'ada@example.com',
            'google_id' => null,
        ]);

        $user = app(ResolveGoogleUser::class)->handle(SocialiteUser::fake([
            'id' => 'google-123',
            'email' => 'ada@example.com',
        ]));

        $this->assertTrue($existing->is($user));
        $this->assertDatabaseHas('users', [
            'id' => $existing->id,
            'google_id' => 'google-123',
        ]);
    }

    public function test_frontend_can_exchange_a_google_handoff_code_for_an_api_token(): void
    {
        $user = User::factory()->hasProfile()->create();
        $code = str_repeat('a', 64);
        Cache::put("oauth:handoff:{$code}", $user->id, now()->addMinutes(5));

        $response = $this->postJson('/api/v1/auth/oauth/exchange', [
            'code' => $code,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('user.email', $user->email)
            ->assertJsonStructure(['token']);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'learner',
        ]);
        $this->assertNull(Cache::get("oauth:handoff:{$code}"));
    }

    public function test_handoff_code_can_only_be_used_once(): void
    {
        $user = User::factory()->create();
        $code = str_repeat('b', 64);
        Cache::put("oauth:handoff:{$code}", $user->id, now()->addMinutes(5));

        $this->postJson('/api/v1/auth/oauth/exchange', [
            'code' => $code,
        ])->assertOk();

        $this->postJson('/api/v1/auth/oauth/exchange', [
            'code' => $code,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }
}
