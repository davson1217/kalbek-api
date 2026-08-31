<?php

namespace Tests\Unit\Services\Ai;

use App\Models\Goal;
use App\Models\NpcLine;
use App\Models\Scenario;
use App\Models\Scene;
use App\Services\Ai\DialogueOrchestrator;
use Database\Seeders\CharacterSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DialogueOrchestratorTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_failed_goal_stays_in_current_scene_without_reply_trigger(): void
    {
        [$scenario, $scene, $goal] = $this->scenarioWithGoal(nextScene: null);

        $decision = app(DialogueOrchestrator::class)->decide(
            $scenario,
            $scene,
            $goal,
            'Nežinau.',
            passed: false,
        );

        $this->assertSame([
            'matched_goal_id' => 'current-goal',
            'additional_goal_ids' => [],
            'next_scene_id' => 'current-scene',
            'reply_scene_id' => 'current-scene',
            'reply_trigger_goal_id' => null,
            'should_complete' => false,
            'reason' => 'Selected goal did not pass.',
            'provider' => null,
            'model' => null,
        ], $decision);
    }

    public function test_passed_terminal_goal_completes_after_current_scene_reply(): void
    {
        [$scenario, $scene, $goal] = $this->scenarioWithGoal(nextScene: null);
        NpcLine::factory()->for($scene)->create(['trigger_goal_id' => $goal->id]);

        $decision = app(DialogueOrchestrator::class)->decide(
            $scenario,
            $scene,
            $goal,
            'Ačiū, viskas.',
            passed: true,
        );

        $this->assertSame('current-goal', $decision['matched_goal_id']);
        $this->assertNull($decision['next_scene_id']);
        $this->assertSame('current-scene', $decision['reply_scene_id']);
        $this->assertSame('current-goal', $decision['reply_trigger_goal_id']);
        $this->assertTrue($decision['should_complete']);
        $this->assertSame('Selected goal completes the scenario.', $decision['reason']);
    }

    public function test_passed_goal_uses_current_scene_reply_when_authored_for_selected_goal(): void
    {
        [$scenario, $scene, $goal, $nextScene] = $this->scenarioWithGoal(nextScene: 'next-scene');
        NpcLine::factory()->for($scene)->create(['trigger_goal_id' => $goal->id]);

        $decision = app(DialogueOrchestrator::class)->decide(
            $scenario,
            $scene,
            $goal,
            'Kiek tai kainuoja?',
            passed: true,
        );

        $this->assertSame('next-scene', $nextScene->slug);
        $this->assertSame('next-scene', $decision['next_scene_id']);
        $this->assertSame('current-scene', $decision['reply_scene_id']);
        $this->assertSame('current-goal', $decision['reply_trigger_goal_id']);
        $this->assertFalse($decision['should_complete']);
    }

    public function test_passed_goal_falls_back_to_next_scene_reply_when_current_scene_has_no_matching_reply(): void
    {
        [$scenario, $scene, $goal] = $this->scenarioWithGoal(nextScene: 'next-scene');
        NpcLine::factory()->for($scene)->create(['trigger_goal_id' => null]);

        $decision = app(DialogueOrchestrator::class)->decide(
            $scenario,
            $scene,
            $goal,
            'Kiek tai kainuoja?',
            passed: true,
        );

        $this->assertSame('next-scene', $decision['next_scene_id']);
        $this->assertSame('next-scene', $decision['reply_scene_id']);
        $this->assertSame('current-goal', $decision['reply_trigger_goal_id']);
        $this->assertSame('Advance by the selected authored goal.', $decision['reason']);
    }

    /**
     * @return array{0: Scenario, 1: Scene, 2: Goal, 3?: Scene}
     */
    private function scenarioWithGoal(?string $nextScene): array
    {
        $this->seed(CharacterSeeder::class);

        $scenario = Scenario::factory()->create([
            'slug' => 'flow-test',
            'start_scene_slug' => 'current-scene',
        ]);
        $scene = Scene::factory()->for($scenario)->create(['slug' => 'current-scene']);
        $next = $nextScene ? Scene::factory()->for($scenario)->create(['slug' => $nextScene]) : null;
        $goal = Goal::factory()->for($scene)->create([
            'slug' => 'current-goal',
            'next_scene_id' => $next?->id,
        ]);

        return array_values(array_filter([$scenario, $scene, $goal, $next]));
    }
}
