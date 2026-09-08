<?php

namespace Tests\Feature;

use App\Models\Scenario;
use Database\Seeders\A1ScenarioSeeder;
use Database\Seeders\CharacterSeeder;
use Database\Seeders\EnglishShopScenarioSeeder;
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
            ->assertJsonPath('data.0.language.code', 'lt')
            ->assertJsonPath('data.0.language.support_language.code', 'en')
            ->assertJsonPath('data.0.character.id', 'rasa')
            ->assertJsonMissing(['id' => 'draft-scene']);
    }

    public function test_active_languages_are_listed_for_the_learner_api(): void
    {
        $this->seed([CharacterSeeder::class, RestaurantScenarioSeeder::class]);

        $this->getJson('/api/v1/languages')
            ->assertOk()
            ->assertJsonPath('data.0.code', 'lt')
            ->assertJsonPath('data.0.name', 'Lithuanian')
            ->assertJsonPath('data.0.support_language.code', 'en')
            ->assertJsonPath('data.0.support_language.name', 'English');
    }

    public function test_english_shop_scenario_is_listed_and_served_by_language(): void
    {
        $this->seed([CharacterSeeder::class, EnglishShopScenarioSeeder::class]);

        $this->getJson('/api/v1/scenarios?language=en')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', 'at-the-shop')
            ->assertJsonPath('data.0.title', 'At the shop')
            ->assertJsonPath('data.0.language.code', 'en')
            ->assertJsonPath('data.0.character.id', 'emily');

        $this->getJson('/api/v1/scenarios/at-the-shop')
            ->assertOk()
            ->assertJsonPath('data.language.code', 'en')
            ->assertJsonPath('data.language.support_language.code', 'en')
            ->assertJsonPath('data.start_scene_id', 'looking-for-items')
            ->assertJsonCount(3, 'data.scenes')
            ->assertJsonPath('data.scenes.0.goals.0.id', 'en-shop-milk')
            ->assertJsonPath('data.scenes.0.goals.0.next', 'price-and-bag')
            ->assertJsonFragment(['target_text' => 'Hello! Can I help you?'])
            ->assertJsonFragment(['trigger_goal_id' => 'en-shop-price'])
            ->assertJsonFragment(['id' => 'en-shop-goodbye', 'next' => null]);
    }

    public function test_restaurant_scenario_detail_includes_the_scene_graph(): void
    {
        $this->seed([CharacterSeeder::class, RestaurantScenarioSeeder::class]);

        $response = $this->getJson('/api/v1/scenarios/restoranas');

        $response
            ->assertOk()
            ->assertJsonPath('data.id', 'restoranas')
            ->assertJsonPath('data.language.code', 'lt')
            ->assertJsonPath('data.language.support_language.name', 'English')
            ->assertJsonPath('data.cefr_level', 'a1')
            ->assertJsonPath('data.start_scene_id', 'atvykimas')
            ->assertJsonPath('data.character.id', 'rasa')
            ->assertJsonPath('data.scenes.0.id', 'atvykimas')
            ->assertJsonPath('data.scenes.0.cefr_level', 'a1')
            ->assertJsonPath('data.scenes.0.goals.0.next', 'sodinimas')
            ->assertJsonPath('data.scenes.0.goals.0.cefr_level', 'a1')
            ->assertJsonPath('data.scenes.1.id', 'sodinimas')
            ->assertJsonPath('data.scenes.2.id', 'meniu')
            ->assertJsonPath('data.scenes.2.props.0.target_text', 'Sriuba')
            ->assertJsonFragment(['target_text' => 'Laba diena! Kuo galiu jums padėti?'])
            ->assertJsonFragment(['target_text' => 'Prašom, sėskitės čia.'])
            ->assertJsonFragment(['trigger_goal_id' => 'order-soup'])
            ->assertJsonCount(5, 'data.scenes')
            ->assertJsonCount(5, 'data.scenes.2.props');
    }

    public function test_scenario_payload_uses_requested_app_language_translations(): void
    {
        $this->seed([CharacterSeeder::class, RestaurantScenarioSeeder::class]);
        $scenario = Scenario::query()
            ->where('slug', 'restoranas')
            ->with(['scenes.goals', 'scenes.npcLines'])
            ->firstOrFail();
        $scene = $scenario->scenes->first();
        $goal = $scene->goals->first();
        $line = $scene->npcLines->first();

        $scenario->translations()->createMany([
            ['field' => 'title', 'locale' => 'lt', 'value' => 'Restoranas'],
            ['field' => 'subtitle', 'locale' => 'lt', 'value' => 'Restorano pokalbis'],
            ['field' => 'description', 'locale' => 'lt', 'value' => 'Mokykitės užsisakyti restorane.'],
        ]);
        $scene->translations()->createMany([
            ['field' => 'title', 'locale' => 'lt', 'value' => 'Atvykimas'],
            ['field' => 'setting', 'locale' => 'lt', 'value' => 'Įeinate į restoraną.'],
        ]);
        $goal->translations()->createMany([
            ['field' => 'label', 'locale' => 'lt', 'value' => 'Paprašykite staliuko'],
            ['field' => 'intent', 'locale' => 'lt', 'value' => 'Mandagiai pasisveikinti ir paprašyti staliuko.'],
        ]);
        $line->translations()->create([
            'field' => 'support_translation',
            'locale' => 'lt',
            'value' => 'Mandagus restorano pasisveikinimas.',
        ]);
        $note = $scenario->note()->updateOrCreate([], [
            'title' => 'Before the restaurant',
            'body' => 'Practise asking for a table.',
            'cefr_level' => 'a1',
            'estimated_minutes' => 2,
            'status' => 'published',
        ]);
        $note->translations()->updateOrCreate(
            ['field' => 'title', 'locale' => 'lt'],
            ['value' => 'Prieš restoraną'],
        );
        $note->translations()->updateOrCreate(
            ['field' => 'body', 'locale' => 'lt'],
            ['value' => 'Mokykitės paprašyti staliuko.'],
        );

        $this->getJson('/api/v1/scenarios/restoranas?app_language=lt')
            ->assertOk()
            ->assertJsonPath('data.title', 'Restoranas')
            ->assertJsonPath('data.subtitle', 'Restorano pokalbis')
            ->assertJsonPath('data.description', 'Mokykitės užsisakyti restorane.')
            ->assertJsonPath('data.scenes.0.title', 'Atvykimas')
            ->assertJsonPath('data.scenes.0.setting', 'Įeinate į restoraną.')
            ->assertJsonPath('data.note.title', 'Prieš restoraną')
            ->assertJsonPath('data.note.body', 'Mokykitės paprašyti staliuko.')
            ->assertJsonPath('data.note.estimated_minutes', 2)
            ->assertJsonPath('data.scenes.0.goals.0.label', 'Paprašykite staliuko')
            ->assertJsonPath('data.scenes.0.goals.0.intent', 'Mandagiai pasisveikinti ir paprašyti staliuko.')
            ->assertJsonPath('data.scenes.0.lines.0.support_translation', 'Mandagus restorano pasisveikinimas.');
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

    public function test_current_seeded_scenarios_have_preparation_notes(): void
    {
        $this->seed([
            CharacterSeeder::class,
            A1ScenarioSeeder::class,
            RestaurantScenarioSeeder::class,
            PharmacyVisitScenarioSeeder::class,
            EnglishShopScenarioSeeder::class,
        ]);

        $this->assertSame(11, Scenario::query()->count());
        $this->assertSame(11, Scenario::query()->has('note')->count());

        $this->getJson('/api/v1/scenarios/prisistatymas')
            ->assertOk()
            ->assertJsonPath('data.note.title', 'Before you introduce yourself')
            ->assertJsonPath('data.note.cefr_level', 'a1');

        $this->getJson('/api/v1/scenarios/pharmacy-visit')
            ->assertOk()
            ->assertJsonPath('data.note.title', 'Before you speak at a pharmacy');

        $this->getJson('/api/v1/scenarios/at-the-shop')
            ->assertOk()
            ->assertJsonPath('data.note.title', 'Before you shop for simple items');
    }

    public function test_draft_scenarios_return_404_from_the_learner_api(): void
    {
        $scenario = Scenario::factory()->draft()->create(['slug' => 'not-ready']);

        $response = $this->getJson("/api/v1/scenarios/{$scenario->slug}");

        $response->assertNotFound();
    }
}
