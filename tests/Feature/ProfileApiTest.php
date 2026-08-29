<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\User;
use Database\Seeders\CharacterSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_authenticated_user_can_view_their_profile(): void
    {
        $this->seed(CharacterSeeder::class);
        $character = Character::query()->where('slug', 'gabija')->firstOrFail();
        $user = User::factory()->create(['name' => 'Ada']);
        $user->profile()->create([
            'avatar_character_id' => $character->id,
            'display_name' => 'Ada',
        ]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/profile');

        $response
            ->assertOk()
            ->assertJsonPath('data.display_name', 'Ada')
            ->assertJsonPath('data.avatar_character.id', 'gabija');
    }

    public function test_authenticated_user_can_update_profile_display_name_and_avatar(): void
    {
        $this->seed(CharacterSeeder::class);
        $gabija = Character::query()->where('slug', 'gabija')->firstOrFail();
        $user = User::factory()->create(['name' => 'Ada']);
        $user->profile()->create([
            'avatar_character_id' => $gabija->id,
            'display_name' => 'Ada',
        ]);
        Sanctum::actingAs($user);

        $response = $this->patchJson('/api/v1/profile', [
            'display_name' => 'Adele',
            'avatar_character' => 'rasa',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.display_name', 'Adele')
            ->assertJsonPath('data.avatar_character.id', 'rasa');

        $this->assertDatabaseHas('profiles', [
            'user_id' => $user->id,
            'display_name' => 'Adele',
        ]);
    }

    public function test_profile_avatar_must_be_an_existing_character_slug(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/profile', [
            'avatar_character' => 'missing-character',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('avatar_character');
    }
}
