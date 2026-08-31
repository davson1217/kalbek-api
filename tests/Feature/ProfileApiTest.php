<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\LearnerLanguageLevel;
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
            ->assertJsonPath('data.avatar_character.id', 'gabija')
            ->assertJsonPath('data.strict_speech_mode', false)
            ->assertJsonPath('data.language_level.current_cefr_level', 'pre_a1')
            ->assertJsonPath('data.language_level.confidence_score', 0)
            ->assertJsonPath('data.language_level.evidence_attempts_count', 0);
    }

    public function test_authenticated_user_can_view_derived_language_level(): void
    {
        $this->seed(CharacterSeeder::class);
        $character = Character::query()->where('slug', 'gabija')->firstOrFail();
        $user = User::factory()->create(['name' => 'Ada']);
        $user->profile()->create([
            'avatar_character_id' => $character->id,
            'display_name' => 'Ada',
        ]);
        LearnerLanguageLevel::query()->create([
            'user_id' => $user->id,
            'language_code' => 'lt',
            'current_cefr_level' => 'a1',
            'confidence_score' => 70,
            'grammar_score' => 76,
            'vocabulary_score' => 72,
            'cohesion_score' => 68,
            'task_completion_score' => 84,
            'evidence_attempts_count' => 10,
            'last_evaluated_at' => now(),
        ]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/profile');

        $response
            ->assertOk()
            ->assertJsonPath('data.language_level.current_cefr_level', 'a1')
            ->assertJsonPath('data.language_level.current_cefr_label', 'A1')
            ->assertJsonPath('data.language_level.confidence_score', 70)
            ->assertJsonPath('data.language_level.grammar_score', 76)
            ->assertJsonPath('data.language_level.evidence_attempts_count', 10);
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
            'strict_speech_mode' => true,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.display_name', 'Adele')
            ->assertJsonPath('data.avatar_character.id', 'rasa')
            ->assertJsonPath('data.strict_speech_mode', true);

        $this->assertDatabaseHas('profiles', [
            'user_id' => $user->id,
            'display_name' => 'Adele',
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'strict_speech_mode' => true,
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
