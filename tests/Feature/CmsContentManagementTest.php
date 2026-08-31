<?php

namespace Tests\Feature;

use App\Models\Goal;
use App\Models\NpcLine;
use App\Models\Scenario;
use App\Models\Scene;
use App\Models\User;
use Database\Seeders\CharacterSeeder;
use Database\Seeders\RestaurantScenarioSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CmsContentManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_can_view_scenarios_index(): void
    {
        $this->seed([CharacterSeeder::class, RestaurantScenarioSeeder::class]);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('cms.scenarios.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Scenarios/Index')
                ->has('scenarios', 1));
    }

    public function test_admin_can_create_scene_goal_and_npc_line(): void
    {
        $this->seed([CharacterSeeder::class, RestaurantScenarioSeeder::class]);
        $admin = User::factory()->create(['role' => 'admin']);
        $scenario = Scenario::query()->where('slug', 'restoranas')->firstOrFail();

        $this->actingAs($admin)->post(route('cms.scenarios.scenes.store', $scenario), [
            'slug' => 'test-scene',
            'setting' => 'A teacher-authored test scene.',
            'cefr_level' => 'a1',
            'sort_order' => 999,
        ])->assertRedirect();

        $scene = Scene::query()->where('scenario_id', $scenario->id)->where('slug', 'test-scene')->firstOrFail();

        $this->actingAs($admin)->post(route('cms.scenarios.scenes.goals.store', [$scenario, $scene]), [
            'slug' => 'test-goal',
            'label' => 'Ask a test question',
            'intent' => 'Ask a short teacher-authored test question.',
            'example' => 'Ar turite testą?',
            'cefr_level' => 'a1',
            'next_scene_id' => null,
            'sort_order' => 10,
        ])->assertRedirect();

        $goal = Goal::query()->where('scene_id', $scene->id)->where('slug', 'test-goal')->firstOrFail();

        $this->actingAs($admin)->post(route('cms.scenarios.scenes.lines.store', [$scenario, $scene]), [
            'lt' => 'Taip, turime testą.',
            'en' => 'Yes, we have a test.',
            'cefr_level' => 'a1',
            'trigger_goal_id' => $goal->id,
            'priority' => 100,
            'sort_order' => 10,
        ])->assertRedirect();

        $this->assertDatabaseHas('npc_lines', [
            'scene_id' => $scene->id,
            'trigger_goal_id' => $goal->id,
            'lt' => 'Taip, turime testą.',
            'cefr_level' => 'a1',
            'priority' => 100,
        ]);

        $this->actingAs($admin)->get(route('cms.scenarios.show', $scenario))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Scenarios/Show')
                ->where('scenario.scenes.5.goals.0.response_lines.0.lt', 'Taip, turime testą.'));
    }

    public function test_character_reply_must_belong_to_a_goal_in_the_same_scene(): void
    {
        $this->seed([CharacterSeeder::class, RestaurantScenarioSeeder::class]);
        $admin = User::factory()->create(['role' => 'admin']);
        $scenario = Scenario::query()->where('slug', 'restoranas')->firstOrFail();
        $scenes = $scenario->scenes()->orderBy('sort_order')->take(2)->get();
        $firstScene = $scenes->first();
        $secondScene = $scenes->last();
        $otherGoal = Goal::query()->where('scene_id', $secondScene->id)->firstOrFail();

        $this->actingAs($admin)->from(route('cms.scenarios.show', $scenario))->post(route('cms.scenarios.scenes.lines.store', [$scenario, $firstScene]), [
            'lt' => 'Taip.',
            'en' => 'Yes.',
            'cefr_level' => 'a1',
            'trigger_goal_id' => $otherGoal->id,
            'priority' => 100,
            'sort_order' => 10,
        ])
            ->assertRedirect(route('cms.scenarios.show', $scenario))
            ->assertSessionHasErrors('trigger_goal_id');

        $this->assertSame(0, NpcLine::query()->where('scene_id', $firstScene->id)->where('lt', 'Taip.')->count());
    }

    public function test_grant_cms_access_command_promotes_existing_user(): void
    {
        $user = User::factory()->create(['email' => 'teacher@example.com', 'role' => 'learner']);

        $this->artisan('kalbek:grant-cms-access teacher@example.com --role=teacher')
            ->expectsOutput('Granted teacher CMS access to teacher@example.com.')
            ->assertSuccessful();

        $this->assertSame('teacher', $user->refresh()->role->value);
    }
}
