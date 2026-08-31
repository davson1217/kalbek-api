<?php

namespace Tests\Feature;

use App\Models\Goal;
use App\Models\NpcLine;
use App\Models\Scenario;
use App\Models\Scene;
use Database\Seeders\CharacterSeeder;
use Database\Seeders\ScenarioSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ScenarioContentAuditTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_seeded_scenario_content_passes_the_audit(): void
    {
        $this->seed([CharacterSeeder::class, ScenarioSeeder::class]);

        $this->artisan('kalbek:audit-content --fail')
            ->expectsOutput('Scenario content audit passed.')
            ->assertSuccessful();
    }

    public function test_audit_fails_when_goal_replies_hardcode_example_values(): void
    {
        $this->seed(CharacterSeeder::class);
        $scenario = Scenario::factory()->create(['slug' => 'intro', 'start_scene_slug' => 'start']);
        $scene = Scene::factory()->for($scenario)->create(['slug' => 'start']);
        $goal = Goal::factory()->for($scene)->create([
            'slug' => 'name',
            'label' => 'Say your name',
            'intent' => 'The learner says their name.',
            'example' => 'Aš esu Jonas.',
        ]);

        NpcLine::factory()->for($scene)->create(['trigger_goal_id' => null]);
        NpcLine::factory()->for($scene)->create([
            'trigger_goal_id' => $goal->id,
            'target_text' => 'Malonu, Jonai.',
            'support_translation' => 'Nice to meet you, Jonas.',
        ]);

        $this->artisan('kalbek:audit-content --fail')
            ->expectsOutputToContain('hardcode example value [Jonas]')
            ->assertFailed();
    }

    public function test_audit_fails_when_a_reply_uses_a_goal_from_another_scene(): void
    {
        $this->seed(CharacterSeeder::class);
        $scenario = Scenario::factory()->create(['slug' => 'flow', 'start_scene_slug' => 'one']);
        $firstScene = Scene::factory()->for($scenario)->create(['slug' => 'one']);
        $secondScene = Scene::factory()->for($scenario)->create(['slug' => 'two']);
        $firstGoal = Goal::factory()->for($firstScene)->create(['slug' => 'first']);
        $otherGoal = Goal::factory()->for($secondScene)->create(['slug' => 'second']);

        NpcLine::factory()->for($firstScene)->create(['trigger_goal_id' => null]);
        NpcLine::factory()->for($firstScene)->create(['trigger_goal_id' => $firstGoal->id]);
        NpcLine::factory()->for($secondScene)->create(['trigger_goal_id' => null]);
        NpcLine::factory()->for($secondScene)->create(['trigger_goal_id' => $otherGoal->id]);
        NpcLine::factory()->for($firstScene)->create(['trigger_goal_id' => $otherGoal->id]);

        $this->artisan('kalbek:audit-content --fail')
            ->expectsOutputToContain('is attached to a goal from another scene')
            ->assertFailed();
    }

    public function test_audit_warns_when_scene_is_not_reachable_from_start_scene(): void
    {
        $scenario = Scenario::factory()->create(['slug' => 'directions', 'start_scene_slug' => 'start']);
        $start = Scene::factory()->for($scenario)->create(['slug' => 'start']);
        $next = Scene::factory()->for($scenario)->create(['slug' => 'next']);
        $unreachable = Scene::factory()->for($scenario)->create(['slug' => 'unused']);
        $startGoal = Goal::factory()->for($start)->create(['slug' => 'go-next', 'next_scene_id' => $next->id]);
        $nextGoal = Goal::factory()->for($next)->create(['slug' => 'finish']);
        $unusedGoal = Goal::factory()->for($unreachable)->create(['slug' => 'unused-goal']);

        NpcLine::factory()->for($start)->create(['trigger_goal_id' => null]);
        NpcLine::factory()->for($start)->create(['trigger_goal_id' => $startGoal->id]);
        NpcLine::factory()->for($next)->create(['trigger_goal_id' => null]);
        NpcLine::factory()->for($next)->create(['trigger_goal_id' => $nextGoal->id]);
        NpcLine::factory()->for($unreachable)->create(['trigger_goal_id' => null]);
        NpcLine::factory()->for($unreachable)->create(['trigger_goal_id' => $unusedGoal->id]);

        $this->artisan('kalbek:audit-content --fail')
            ->expectsOutputToContain('is not reachable from the start scene [start]')
            ->assertFailed();
    }

    public function test_audit_fails_when_goal_links_to_scene_outside_scenario(): void
    {
        $scenario = Scenario::factory()->create(['slug' => 'local-flow', 'start_scene_slug' => 'start']);
        $otherScenario = Scenario::factory()->draft()->create(['slug' => 'other-flow', 'start_scene_slug' => 'other']);
        $start = Scene::factory()->for($scenario)->create(['slug' => 'start']);
        $external = Scene::factory()->for($otherScenario)->create(['slug' => 'external']);
        $goal = Goal::factory()->for($start)->create(['slug' => 'leave', 'next_scene_id' => $external->id]);

        NpcLine::factory()->for($start)->create(['trigger_goal_id' => null]);
        NpcLine::factory()->for($start)->create(['trigger_goal_id' => $goal->id]);

        $this->artisan('kalbek:audit-content --fail')
            ->expectsOutputToContain('links to a scene outside this scenario')
            ->assertFailed();
    }

    public function test_audit_warns_when_child_content_exceeds_parent_cefr_level(): void
    {
        $scenario = Scenario::factory()->create([
            'slug' => 'level-flow',
            'cefr_level' => 'a1',
            'start_scene_slug' => 'start',
        ]);
        $scene = Scene::factory()->for($scenario)->create([
            'slug' => 'start',
            'cefr_level' => 'a2',
        ]);
        $goal = Goal::factory()->for($scene)->create([
            'slug' => 'advanced-goal',
            'cefr_level' => 'b1',
        ]);

        NpcLine::factory()->for($scene)->create(['trigger_goal_id' => null, 'cefr_level' => 'a2']);
        NpcLine::factory()->for($scene)->create(['trigger_goal_id' => $goal->id, 'cefr_level' => 'b2']);

        $this->artisan('kalbek:audit-content --fail')
            ->expectsOutputToContain('Scene [level-flow/start] is level [a2] but its scenario is level [a1]')
            ->expectsOutputToContain('Goal [level-flow/start/advanced-goal] is level [b1] but its parent content is level [a2]')
            ->expectsOutputToContain('but its parent content is level [b1]')
            ->assertFailed();
    }
}
