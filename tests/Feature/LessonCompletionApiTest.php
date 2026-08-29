<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\Scenario;
use App\Models\User;
use Database\Seeders\CharacterSeeder;
use Database\Seeders\RestaurantScenarioSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LessonCompletionApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_completion_requires_authentication(): void
    {
        $scenario = Scenario::factory()->create();

        $this->postJson("/api/v1/lessons/{$scenario->slug}/complete", [
            'score' => 85,
            'xp' => 20,
        ])->assertUnauthorized();
    }

    public function test_authenticated_user_can_complete_a_published_lesson(): void
    {
        $this->seed([CharacterSeeder::class, RestaurantScenarioSeeder::class]);
        $scenario = Scenario::query()->where('slug', 'restoranas')->firstOrFail();
        $user = User::factory()->create(['name' => 'Ada']);
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/lessons/{$scenario->slug}/complete", [
            'score' => 88,
            'xp' => 25,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.scenario_id', 'restoranas')
            ->assertJsonPath('data.completed', true)
            ->assertJsonPath('data.best_score', 88)
            ->assertJsonPath('data.xp_earned', 25)
            ->assertJsonPath('data.attempts', 1);

        $this->assertDatabaseHas('lesson_progress', [
            'user_id' => $user->id,
            'scenario_id' => $scenario->id,
            'completed' => true,
            'best_score' => 88,
            'xp_earned' => 25,
            'attempts' => 1,
        ]);
        $this->assertDatabaseHas('profiles', [
            'user_id' => $user->id,
            'xp' => 25,
            'streak' => 1,
        ]);
    }

    public function test_repeated_completion_accumulates_xp_attempts_and_best_score(): void
    {
        $this->seed([CharacterSeeder::class, RestaurantScenarioSeeder::class]);
        $scenario = Scenario::query()->where('slug', 'restoranas')->firstOrFail();
        $user = User::factory()->create();
        Profile::factory()->for($user)->create(['xp' => 10]);
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/lessons/{$scenario->slug}/complete", [
            'score' => 90,
            'xp' => 15,
        ])->assertOk();

        $this->postJson("/api/v1/lessons/{$scenario->slug}/complete", [
            'score' => 70,
            'xp' => 20,
        ])->assertOk()
            ->assertJsonPath('data.best_score', 90)
            ->assertJsonPath('data.xp_earned', 35)
            ->assertJsonPath('data.attempts', 2);

        $this->assertDatabaseHas('profiles', [
            'user_id' => $user->id,
            'xp' => 45,
        ]);
    }

    public function test_authenticated_user_can_list_their_lesson_progress(): void
    {
        $this->seed([CharacterSeeder::class, RestaurantScenarioSeeder::class]);
        $scenario = Scenario::query()->where('slug', 'restoranas')->firstOrFail();
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/lessons/{$scenario->slug}/complete", [
            'score' => 82,
            'xp' => 20,
        ])->assertOk();

        $this->getJson('/api/v1/lesson-progress')
            ->assertOk()
            ->assertJsonPath('data.0.scenario_id', 'restoranas')
            ->assertJsonPath('data.0.completed', true)
            ->assertJsonPath('data.0.best_score', 82)
            ->assertJsonPath('data.0.xp_earned', 20)
            ->assertJsonPath('data.0.attempts', 1);
    }
}
