<?php

namespace Tests\Feature;

use App\Models\Scenario;
use Database\Seeders\A1ScenarioSeeder;
use Database\Seeders\CharacterSeeder;
use Database\Seeders\PharmacyVisitScenarioSeeder;
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
            ->assertJsonPath('data.scenes.0.goals.0.next', 'sodinimas')
            ->assertJsonPath('data.scenes.0.goals.0.cefr_level', 'a1')
            ->assertJsonPath('data.scenes.1.id', 'sodinimas')
            ->assertJsonPath('data.scenes.2.id', 'meniu')
            ->assertJsonPath('data.scenes.2.props.0.lt', 'Sriuba')
            ->assertJsonFragment(['lt' => 'Laba diena! Kuo galiu jums padėti?'])
            ->assertJsonFragment(['lt' => 'Prašom, sėskitės čia.'])
            ->assertJsonFragment(['trigger_goal_id' => 'order-soup'])
            ->assertJsonCount(5, 'data.scenes')
            ->assertJsonCount(5, 'data.scenes.2.props');
    }

    public function test_pharmacy_scenario_detail_is_a_clean_a1_scene_graph(): void
    {
        $this->seed([CharacterSeeder::class, PharmacyVisitScenarioSeeder::class]);

        $response = $this->getJson('/api/v1/scenarios/pharmacy-visit');

        $response
            ->assertOk()
            ->assertJsonPath('data.title', 'Vaistinėje')
            ->assertJsonPath('data.cefr_level', 'a1')
            ->assertJsonPath('data.start_scene_id', 'greeting')
            ->assertJsonCount(4, 'data.scenes')
            ->assertJsonPath('data.scenes.0.id', 'greeting')
            ->assertJsonPath('data.scenes.1.id', 'symptoms')
            ->assertJsonPath('data.scenes.2.id', 'medicine')
            ->assertJsonPath('data.scenes.3.id', 'payment')
            ->assertJsonFragment(['id' => 'headache', 'next' => 'medicine'])
            ->assertJsonFragment(['id' => 'ask-how-to-use', 'next' => 'payment'])
            ->assertJsonFragment(['trigger_goal_id' => 'ask-how-to-use'])
            ->assertJsonFragment(['id' => 'pay-card', 'next' => null]);
    }

    public function test_a1_scenarios_are_seeded_as_published_beginner_scene_graphs(): void
    {
        $this->seed([
            CharacterSeeder::class,
            A1ScenarioSeeder::class,
            RestaurantScenarioSeeder::class,
            PharmacyVisitScenarioSeeder::class,
        ]);

        $response = $this->getJson('/api/v1/scenarios');

        $response
            ->assertOk()
            ->assertJsonFragment(['id' => 'prisistatymas'])
            ->assertJsonFragment(['id' => 'kavineje'])
            ->assertJsonFragment(['id' => 'parduotuveje'])
            ->assertJsonFragment(['id' => 'viesbutyje'])
            ->assertJsonFragment(['id' => 'autobuse'])
            ->assertJsonFragment(['id' => 'mieste'])
            ->assertJsonFragment(['id' => 'pas-gydytoja'])
            ->assertJsonFragment(['id' => 'klaseje'])
            ->assertJsonPath('data.0.id', 'prisistatymas')
            ->assertJsonPath('data.1.id', 'kavineje')
            ->assertJsonPath('data.2.id', 'parduotuveje')
            ->assertJsonPath('data.3.id', 'restoranas')
            ->assertJsonPath('data.4.id', 'pharmacy-visit');

        $detail = $this->getJson('/api/v1/scenarios/kavineje');

        $detail
            ->assertOk()
            ->assertJsonPath('data.cefr_level', 'a1')
            ->assertJsonPath('data.start_scene_id', 'uzsakymas')
            ->assertJsonCount(3, 'data.scenes')
            ->assertJsonPath('data.scenes.0.goals.0.id', 'cafe-coffee')
            ->assertJsonPath('data.scenes.0.goals.0.next', 'priedai')
            ->assertJsonFragment(['trigger_goal_id' => 'cafe-coffee'])
            ->assertJsonFragment(['id' => 'cafe-pay-card', 'next' => null]);
    }

    public function test_draft_scenarios_return_404_from_the_learner_api(): void
    {
        $scenario = Scenario::factory()->draft()->create(['slug' => 'not-ready']);

        $response = $this->getJson("/api/v1/scenarios/{$scenario->slug}");

        $response->assertNotFound();
    }
}
