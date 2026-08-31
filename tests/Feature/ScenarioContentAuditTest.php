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
            'lt' => 'Malonu, Jonai.',
            'en' => 'Nice to meet you, Jonas.',
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
}
