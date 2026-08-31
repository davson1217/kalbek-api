<?php

namespace Tests\Feature;

use App\Models\Goal;
use App\Models\Scenario;
use App\Models\Scene;
use App\Models\SpeakingAttempt;
use App\Models\User;
use App\SpeakingAttemptStatus;
use Database\Seeders\CharacterSeeder;
use Database\Seeders\RestaurantScenarioSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CefrLevelRecalculationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_cefr_level_is_not_promoted_without_enough_evidence(): void
    {
        [$user, $scenario, $scene, $goal] = $this->seedContent();

        $this->createAttempts($user, $scenario, $scene, $goal, 9, 90);

        $this->artisan('kalbek:recalculate-cefr-levels')
            ->assertSuccessful();

        $this->assertDatabaseHas('learner_language_levels', [
            'user_id' => $user->id,
            'current_cefr_level' => 'pre_a1',
            'confidence_score' => 90,
            'evidence_attempts_count' => 9,
        ]);
    }

    public function test_cefr_level_promotes_only_one_step_after_sustained_evidence(): void
    {
        [$user, $scenario, $scene, $goal] = $this->seedContent();

        $this->createAttempts($user, $scenario, $scene, $goal, 10, 82);

        $this->artisan('kalbek:recalculate-cefr-levels')
            ->expectsOutput('Recalculated CEFR levels for 1 learner(s).')
            ->assertSuccessful();

        $this->assertDatabaseHas('learner_language_levels', [
            'user_id' => $user->id,
            'current_cefr_level' => 'a1',
            'confidence_score' => 100,
            'evidence_attempts_count' => 10,
        ]);
        $this->assertDatabaseHas('cefr_level_histories', [
            'user_id' => $user->id,
            'previous_cefr_level' => 'pre_a1',
            'new_cefr_level' => 'a1',
            'evidence_attempts_count' => 10,
        ]);
    }

    private function seedContent(): array
    {
        $this->seed([CharacterSeeder::class, RestaurantScenarioSeeder::class]);
        $user = User::factory()->create();
        $scenario = Scenario::query()->where('slug', 'restoranas')->firstOrFail();
        $scene = Scene::query()->where('scenario_id', $scenario->id)->where('slug', 'atvykimas')->firstOrFail();
        $goal = Goal::query()->where('scene_id', $scene->id)->where('slug', 'ask-table')->firstOrFail();

        return [$user, $scenario, $scene, $goal];
    }

    private function createAttempts(User $user, Scenario $scenario, Scene $scene, Goal $goal, int $count, int $score): void
    {
        for ($i = 0; $i < $count; $i++) {
            SpeakingAttempt::query()->create([
                'user_id' => $user->id,
                'scenario_id' => $scenario->id,
                'scene_id' => $scene->id,
                'goal_id' => $goal->id,
                'status' => SpeakingAttemptStatus::Graded,
                'transcript' => 'Norėčiau staliuko dviem.',
                'passed' => true,
                'score' => $score,
                'grammar_score' => $score,
                'vocabulary_score' => $score,
                'cohesion_score' => $score,
                'task_completion_score' => $score,
                'overall_score' => $score,
                'attempt_cefr_level' => 'a1',
                'evaluated_at' => now()->subDays($count - $i),
                'graded_at' => now()->subDays($count - $i),
            ]);
        }
    }
}
