<?php

namespace Tests\Feature;

use App\Models\Scenario;
use App\Models\Unit;
use Database\Seeders\A1ScenarioSeeder;
use Database\Seeders\CharacterSeeder;
use Database\Seeders\EnglishShopScenarioSeeder;
use Database\Seeders\KurVietosIrKryptysUnitSeeder;
use Database\Seeders\MaistasIrGerimaiUnitSeeder;
use Database\Seeders\PharmacyVisitScenarioSeeder;
use Database\Seeders\RestaurantScenarioSeeder;
use Database\Seeders\SeimaIrZmonesUnitSeeder;
use Database\Seeders\SusipazinkimeUnitSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ScenarioApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_published_scenarios_are_listed_for_the_learner_api(): void
    {
        $this->seed([CharacterSeeder::class, RestaurantScenarioSeeder::class]);
        $scenario = Scenario::query()->where('slug', 'restoranas')->firstOrFail();
        $unit = Unit::factory()->create([
            'language_id' => $scenario->language_id,
            'slug' => 'susipazinkime',
            'title' => 'Susipažinkime',
            'description' => 'A first unit for introductions.',
            'sort_order' => 5,
        ]);
        $scenario->update(['unit_id' => $unit->id]);
        Scenario::factory()->draft()->create(['slug' => 'draft-scene']);

        $response = $this->getJson('/api/v1/scenarios');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.id', 'restoranas')
            ->assertJsonPath('data.0.title', 'Restorane')
            ->assertJsonPath('data.0.unit.id', 'susipazinkime')
            ->assertJsonPath('data.0.unit.title', 'Susipažinkime')
            ->assertJsonPath('data.0.language.code', 'lt')
            ->assertJsonPath('data.0.language.support_language.code', 'en')
            ->assertJsonPath('data.0.character.id', 'rasa')
            ->assertJsonMissing(['id' => 'draft-scene']);
    }

    public function test_published_units_are_listed_with_published_scenarios(): void
    {
        $this->seed([CharacterSeeder::class, RestaurantScenarioSeeder::class]);
        $scenario = Scenario::query()->where('slug', 'restoranas')->firstOrFail();
        $unit = Unit::factory()->create([
            'language_id' => $scenario->language_id,
            'slug' => 'susipazinkime',
            'title' => 'Susipažinkime',
            'description' => 'A first unit for introductions.',
            'sort_order' => 5,
        ]);
        $unit->translations()->create([
            'field' => 'title',
            'locale' => 'en',
            'value' => 'Let’s Get Acquainted',
        ]);
        $scenario->update(['unit_id' => $unit->id]);
        Scenario::factory()->draft()->create([
            'unit_id' => $unit->id,
            'slug' => 'draft-in-unit',
        ]);
        Unit::factory()->draft()->create([
            'language_id' => $scenario->language_id,
            'slug' => 'draft-unit',
        ]);

        $this->getJson('/api/v1/units?language=lt&app_language=en')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', 'susipazinkime')
            ->assertJsonPath('data.0.title', 'Let’s Get Acquainted')
            ->assertJsonPath('data.0.scenarios_count', 1)
            ->assertJsonPath('data.0.scenarios.0.id', 'restoranas')
            ->assertJsonMissing(['id' => 'draft-in-unit'])
            ->assertJsonMissing(['id' => 'draft-unit']);
    }

    public function test_susipazinkime_unit_seeded_content_is_listed_in_learning_order(): void
    {
        $this->seed([CharacterSeeder::class, SusipazinkimeUnitSeeder::class]);

        $this->getJson('/api/v1/units?language=lt&app_language=en')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', 'susipazinkime')
            ->assertJsonPath('data.0.title', 'Let’s Get Acquainted')
            ->assertJsonPath('data.0.scenarios_count', 6)
            ->assertJsonPath('data.0.scenarios.0.id', 'pasisveikinimas')
            ->assertJsonPath('data.0.scenarios.1.id', 'mano-vardas')
            ->assertJsonPath('data.0.scenarios.2.id', 'ar-jus-esate')
            ->assertJsonPath('data.0.scenarios.3.id', 'is-kur-esate')
            ->assertJsonPath('data.0.scenarios.4.id', 'kas-jus-esate')
            ->assertJsonPath('data.0.scenarios.5.id', 'prisistatymas');

        $this->getJson('/api/v1/scenarios/mano-vardas?app_language=en')
            ->assertOk()
            ->assertJsonPath('data.unit.id', 'susipazinkime')
            ->assertJsonPath('data.note.title', 'Before you say your name')
            ->assertJsonPath('data.start_scene_id', 'vardas')
            ->assertJsonFragment(['id' => 'say-name', 'next' => 'klauskite-vardo'])
            ->assertJsonFragment(['target_text' => 'Sveiki. Koks jūsų vardas?'])
            ->assertJsonFragment(['trigger_goal_id' => 'ask-name']);
    }

    public function test_maistas_ir_gerimai_unit_seeded_content_is_listed_in_learning_order(): void
    {
        $this->seed([CharacterSeeder::class, MaistasIrGerimaiUnitSeeder::class]);

        $this->getJson('/api/v1/units?language=lt&app_language=en')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', 'maistas-ir-gerimai')
            ->assertJsonPath('data.0.title', 'Food and Drinks')
            ->assertJsonPath('data.0.scenarios_count', 6)
            ->assertJsonPath('data.0.scenarios.0.id', 'kavineje')
            ->assertJsonPath('data.0.scenarios.1.id', 'pienas-ar-cukrus')
            ->assertJsonPath('data.0.scenarios.2.id', 'kiek-kainuoja')
            ->assertJsonPath('data.0.scenarios.3.id', 'mokame-uz-gerima')
            ->assertJsonPath('data.0.scenarios.4.id', 'uzkandis-ir-gerimas')
            ->assertJsonPath('data.0.scenarios.5.id', 'restoranas');

        $this->getJson('/api/v1/scenarios/restoranas?app_language=en')
            ->assertOk()
            ->assertJsonPath('data.unit.id', 'maistas-ir-gerimai')
            ->assertJsonPath('data.note.title', 'Before the restaurant capstone')
            ->assertJsonPath('data.start_scene_id', 'atvykimas')
            ->assertJsonFragment(['id' => 'ask-table', 'next' => 'meniu'])
            ->assertJsonFragment(['id' => 'order-food-drink', 'next' => 'mokejimas'])
            ->assertJsonFragment(['example' => 'Norėčiau sriubos ir vandens, prašau.'])
            ->assertJsonFragment(['trigger_goal_id' => 'restaurant-pay-card']);
    }

    public function test_kur_vietos_ir_kryptys_unit_seeded_content_is_listed_in_learning_order(): void
    {
        $this->seed([CharacterSeeder::class, KurVietosIrKryptysUnitSeeder::class]);

        $this->getJson('/api/v1/units?language=lt&app_language=en')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', 'kur-vietos-ir-kryptys')
            ->assertJsonPath('data.0.title', 'Where? Places and Directions')
            ->assertJsonPath('data.0.scenarios_count', 6)
            ->assertJsonPath('data.0.scenarios.0.id', 'kur-as-esu')
            ->assertJsonPath('data.0.scenarios.1.id', 'kur-yra-tualetas')
            ->assertJsonPath('data.0.scenarios.2.id', 'cia-ar-ten')
            ->assertJsonPath('data.0.scenarios.3.id', 'mieste-kur-yra')
            ->assertJsonPath('data.0.scenarios.4.id', 'suprantu-krypti')
            ->assertJsonPath('data.0.scenarios.5.id', 'trumpa-pagalba-mieste');

        $this->getJson('/api/v1/scenarios/trumpa-pagalba-mieste?app_language=en')
            ->assertOk()
            ->assertJsonPath('data.unit.id', 'kur-vietos-ir-kryptys')
            ->assertJsonPath('data.note.title', 'Before: Short Help in Town')
            ->assertJsonPath('data.start_scene_id', 'pagalba')
            ->assertJsonFragment(['id' => 'ask-for-city-help', 'next' => null])
            ->assertJsonFragment(['example' => 'Atsiprašau, kur yra vaistinė?'])
            ->assertJsonFragment(['trigger_goal_id' => 'ask-for-city-help']);
    }

    public function test_seima_ir_zmones_unit_seeded_content_is_listed_in_learning_order(): void
    {
        $this->seed([CharacterSeeder::class, SeimaIrZmonesUnitSeeder::class]);

        $this->getJson('/api/v1/units?language=lt&app_language=en')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', 'seima-ir-zmones')
            ->assertJsonPath('data.0.title', 'Family and People')
            ->assertJsonPath('data.0.scenarios_count', 6)
            ->assertJsonPath('data.0.scenarios.0.id', 'mano-seima')
            ->assertJsonPath('data.0.scenarios.1.id', 'kas-cia')
            ->assertJsonPath('data.0.scenarios.2.id', 'jo-jos-vardas')
            ->assertJsonPath('data.0.scenarios.3.id', 'ar-turite-vaiku')
            ->assertJsonPath('data.0.scenarios.4.id', 'draugas-ar-kolega')
            ->assertJsonPath('data.0.scenarios.5.id', 'pristatykite-zmogu');

        $this->getJson('/api/v1/scenarios/pristatykite-zmogu?app_language=en')
            ->assertOk()
            ->assertJsonPath('data.unit.id', 'seima-ir-zmones')
            ->assertJsonPath('data.note.title', 'Before: Introduce Someone')
            ->assertJsonPath('data.start_scene_id', 'pristatymas')
            ->assertJsonFragment(['id' => 'introduce-person', 'next' => null])
            ->assertJsonFragment(['example' => 'Čia mano draugė. Jos vardas Rūta.'])
            ->assertJsonFragment(['trigger_goal_id' => 'introduce-person']);
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
        $line = $scene->npcLines->whereNull('trigger_goal_id')->sortBy('sort_order')->first();

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
            SusipazinkimeUnitSeeder::class,
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
            ->assertJsonPath('data.0.id', 'pasisveikinimas')
            ->assertJsonPath('data.1.id', 'mano-vardas')
            ->assertJsonPath('data.2.id', 'ar-jus-esate')
            ->assertJsonPath('data.3.id', 'is-kur-esate')
            ->assertJsonPath('data.4.id', 'kas-jus-esate')
            ->assertJsonPath('data.5.id', 'prisistatymas');

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
            SusipazinkimeUnitSeeder::class,
            RestaurantScenarioSeeder::class,
            PharmacyVisitScenarioSeeder::class,
            EnglishShopScenarioSeeder::class,
        ]);

        $this->assertSame(16, Scenario::query()->count());
        $this->assertSame(16, Scenario::query()->has('note')->count());

        $this->getJson('/api/v1/scenarios/prisistatymas')
            ->assertOk()
            ->assertJsonPath('data.note.title', 'Before your short introduction')
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
