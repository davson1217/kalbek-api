<?php

namespace Tests\Feature;

use App\Models\Scenario;
use Database\Seeders\CharacterSeeder;
use Database\Seeders\RestaurantScenarioSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ScenarioApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_published_scenarios_are_listed_for_the_learner_api(): void
    {
        $this->seed([CharacterSeeder::class, RestaurantScenarioSeeder::class]);
        Scenario::factory()->draft()->create(['slug' => 'draft-scene']);

        $response = $this->getJson('/api/v1/scenarios');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.id', 'restoranas')
            ->assertJsonPath('data.0.title', 'Restorane')
            ->assertJsonPath('data.0.character.id', 'rasa')
            ->assertJsonMissing(['id' => 'draft-scene']);
    }

    public function test_restaurant_scenario_detail_includes_the_scene_graph(): void
    {
        $this->seed([CharacterSeeder::class, RestaurantScenarioSeeder::class]);

        $response = $this->getJson('/api/v1/scenarios/restoranas');

        $response
            ->assertOk()
            ->assertJsonPath('data.id', 'restoranas')
            ->assertJsonPath('data.cefr_level', 'a1')
            ->assertJsonPath('data.start_scene_id', 'atvykimas')
            ->assertJsonPath('data.character.id', 'rasa')
            ->assertJsonPath('data.scenes.0.id', 'atvykimas')
            ->assertJsonPath('data.scenes.0.cefr_level', 'a1')
            ->assertJsonPath('data.scenes.0.lines.0.lt', 'Laba diena! Sveiki atvykę į mūsų restoraną.')
            ->assertJsonPath('data.scenes.0.lines.0.cefr_level', 'a1')
            ->assertJsonPath('data.scenes.0.goals.0.next', 'sodinimas')
            ->assertJsonPath('data.scenes.0.goals.0.cefr_level', 'a1')
            ->assertJsonPath('data.scenes.1.id', 'sodinimas')
            ->assertJsonPath('data.scenes.1.lines.0.lt', 'Žinoma. Prašau sekite paskui mane — štai staliukas prie lango.')
            ->assertJsonPath('data.scenes.1.lines.0.trigger_goal_id', 'staliukas')
            ->assertJsonPath('data.scenes.1.lines.0.priority', 100)
            ->assertJsonPath('data.scenes.1.lines.1.lt', 'Turime laisvą staliuką. Prašom sėstis.')
            ->assertJsonPath('data.scenes.1.lines.1.trigger_goal_id', 'staliukas')
            ->assertJsonPath('data.scenes.1.lines.1.priority', 80)
            ->assertJsonPath('data.scenes.2.id', 'meniu')
            ->assertJsonPath('data.scenes.2.props.0.lt', 'Šaltibarščiai')
            ->assertJsonCount(10, 'data.scenes')
            ->assertJsonCount(6, 'data.scenes.2.props');
    }

    public function test_draft_scenarios_return_404_from_the_learner_api(): void
    {
        $scenario = Scenario::factory()->draft()->create(['slug' => 'not-ready']);

        $response = $this->getJson("/api/v1/scenarios/{$scenario->slug}");

        $response->assertNotFound();
    }
}
