<?php

namespace Tests\Feature;

use App\ContentStatus;
use App\Models\Scenario;
use App\Models\Unit;
use Database\Seeders\A1ReviewCapstoneUnitSeeder;
use Database\Seeders\A1ScenarioSeeder;
use Database\Seeders\CharacterSeeder;
use Database\Seeders\EnglishShopScenarioSeeder;
use Database\Seeders\GebejimaiPoreikiaiUnitSeeder;
use Database\Seeders\KelioneApgyvendinimasUnitSeeder;
use Database\Seeders\KlasejeMokantisUnitSeeder;
use Database\Seeders\KurVietosIrKryptysUnitSeeder;
use Database\Seeders\LaisvalaikisPomegiaiUnitSeeder;
use Database\Seeders\MaistasIrGerimaiUnitSeeder;
use Database\Seeders\ManoDienaUnitSeeder;
use Database\Seeders\NamaiDaiktaiUnitSeeder;
use Database\Seeders\OrasDrabuziaiUnitSeeder;
use Database\Seeders\ParduotuvejeUnitSeeder;
use Database\Seeders\PharmacyVisitScenarioSeeder;
use Database\Seeders\PlanaiKvietimaiUnitSeeder;
use Database\Seeders\RestaurantScenarioSeeder;
use Database\Seeders\SeimaIrZmonesUnitSeeder;
use Database\Seeders\SkaiciaiLaikasDatosUnitSeeder;
use Database\Seeders\SusipazinkimeUnitSeeder;
use Database\Seeders\SveikataVaistineUnitSeeder;
use Database\Seeders\TransportasKelioneMiesteUnitSeeder;
use Database\Seeders\UnitsSeeder;
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
            ->assertHeader('Deprecation', 'true')
            ->assertHeader('Link', '</api/v1/units>; rel="successor-version"')
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

    public function test_skaiciai_laikas_ir_datos_unit_seeded_content_is_listed_in_learning_order(): void
    {
        $this->seed([CharacterSeeder::class, SkaiciaiLaikasDatosUnitSeeder::class]);

        $this->getJson('/api/v1/units?language=lt&app_language=en')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', 'skaiciai-laikas-ir-datos')
            ->assertJsonPath('data.0.title', 'Numbers, Time and Dates')
            ->assertJsonPath('data.0.scenarios_count', 6)
            ->assertJsonPath('data.0.scenarios.0.id', 'skaiciai-iki-desimt')
            ->assertJsonPath('data.0.scenarios.1.id', 'mano-amzius')
            ->assertJsonPath('data.0.scenarios.2.id', 'telefono-numeris')
            ->assertJsonPath('data.0.scenarios.3.id', 'kiek-valandu')
            ->assertJsonPath('data.0.scenarios.4.id', 'siandien-ar-rytoj')
            ->assertJsonPath('data.0.scenarios.5.id', 'susitikimo-laikas');

        $this->getJson('/api/v1/scenarios/susitikimo-laikas?app_language=en')
            ->assertOk()
            ->assertJsonPath('data.unit.id', 'skaiciai-laikas-ir-datos')
            ->assertJsonPath('data.note.title', 'Before: Meeting Time')
            ->assertJsonPath('data.start_scene_id', 'dienos-pasirinkimas')
            ->assertJsonFragment(['id' => 'choose-meeting-day', 'next' => 'valandos-pasirinkimas'])
            ->assertJsonFragment(['id' => 'choose-meeting-time', 'next' => 'patvirtinimas'])
            ->assertJsonFragment(['example' => 'Trečią valandą.'])
            ->assertJsonFragment(['trigger_goal_id' => 'confirm-meeting']);
    }

    public function test_parduotuveje_unit_seeded_content_is_listed_in_learning_order(): void
    {
        $this->seed([CharacterSeeder::class, ParduotuvejeUnitSeeder::class]);

        $this->getJson('/api/v1/units?language=lt&app_language=en')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', 'parduotuveje')
            ->assertJsonPath('data.0.title', 'At the Shop')
            ->assertJsonPath('data.0.scenarios_count', 6)
            ->assertJsonPath('data.0.scenarios.0.id', 'duonos-ir-pieno')
            ->assertJsonPath('data.0.scenarios.1.id', 'man-reikia')
            ->assertJsonPath('data.0.scenarios.2.id', 'vienas-ar-du')
            ->assertJsonPath('data.0.scenarios.3.id', 'kaina-parduotuveje')
            ->assertJsonPath('data.0.scenarios.4.id', 'prie-kasos')
            ->assertJsonPath('data.0.scenarios.5.id', 'apsipirkimas-parduotuveje');

        $this->getJson('/api/v1/scenarios/apsipirkimas-parduotuveje?app_language=en')
            ->assertOk()
            ->assertJsonPath('data.unit.id', 'parduotuveje')
            ->assertJsonPath('data.note.title', 'Before: Shopping at the Store')
            ->assertJsonPath('data.start_scene_id', 'prekiu-prasymas')
            ->assertJsonFragment(['id' => 'capstone-ask-item', 'next' => 'kiekio-pasirinkimas'])
            ->assertJsonFragment(['id' => 'capstone-quantity', 'next' => 'kaina-ir-mokejimas'])
            ->assertJsonFragment(['example' => 'Mokėsiu kortele.'])
            ->assertJsonFragment(['trigger_goal_id' => 'capstone-pay']);
    }

    public function test_mano_diena_unit_seeded_content_is_listed_in_learning_order(): void
    {
        $this->seed([CharacterSeeder::class, ManoDienaUnitSeeder::class]);

        $this->getJson('/api/v1/units?language=lt&app_language=en')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', 'mano-diena')
            ->assertJsonPath('data.0.title', 'My Day')
            ->assertJsonPath('data.0.scenarios_count', 6)
            ->assertJsonPath('data.0.scenarios.0.id', 'ryte')
            ->assertJsonPath('data.0.scenarios.1.id', 'kada-keliates')
            ->assertJsonPath('data.0.scenarios.2.id', 'kur-einate')
            ->assertJsonPath('data.0.scenarios.3.id', 'ka-veikiate')
            ->assertJsonPath('data.0.scenarios.4.id', 'vakare')
            ->assertJsonPath('data.0.scenarios.5.id', 'mano-dienos-pasakojimas');

        $this->getJson('/api/v1/scenarios/mano-dienos-pasakojimas?app_language=en')
            ->assertOk()
            ->assertJsonPath('data.unit.id', 'mano-diena')
            ->assertJsonPath('data.note.title', 'Before: Talking About My Day')
            ->assertJsonPath('data.start_scene_id', 'rytas')
            ->assertJsonFragment(['id' => 'capstone-morning', 'next' => 'diena'])
            ->assertJsonFragment(['id' => 'capstone-day', 'next' => 'vakaras'])
            ->assertJsonFragment(['example' => 'Vakare ilsiuosi.'])
            ->assertJsonFragment(['trigger_goal_id' => 'capstone-evening']);
    }

    public function test_namai_ir_daiktai_unit_seeded_content_is_listed_in_learning_order(): void
    {
        $this->seed([CharacterSeeder::class, NamaiDaiktaiUnitSeeder::class]);

        $this->getJson('/api/v1/units?language=lt&app_language=en')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', 'namai-ir-daiktai')
            ->assertJsonPath('data.0.title', 'Home and Objects')
            ->assertJsonPath('data.0.scenarios_count', 6)
            ->assertJsonPath('data.0.scenarios.0.id', 'kambariai')
            ->assertJsonPath('data.0.scenarios.1.id', 'kur-yra-daiktas')
            ->assertJsonPath('data.0.scenarios.2.id', 'ka-turite')
            ->assertJsonPath('data.0.scenarios.3.id', 'mano-kambarys')
            ->assertJsonPath('data.0.scenarios.4.id', 'prasau-daikto')
            ->assertJsonPath('data.0.scenarios.5.id', 'namu-pokalbis');

        $this->getJson('/api/v1/scenarios/namu-pokalbis?app_language=en')
            ->assertOk()
            ->assertJsonPath('data.unit.id', 'namai-ir-daiktai')
            ->assertJsonPath('data.note.title', 'Before: Home Conversation')
            ->assertJsonPath('data.start_scene_id', 'kambarys')
            ->assertJsonFragment(['id' => 'capstone-room', 'next' => 'daikto-vieta'])
            ->assertJsonFragment(['id' => 'capstone-object-location', 'next' => 'prasymas'])
            ->assertJsonFragment(['example' => 'Prašau telefono.'])
            ->assertJsonFragment(['trigger_goal_id' => 'capstone-object-request']);
    }

    public function test_transportas_ir_kelione_mieste_unit_seeded_content_is_listed_in_learning_order(): void
    {
        $this->seed([CharacterSeeder::class, TransportasKelioneMiesteUnitSeeder::class]);

        $this->getJson('/api/v1/units?language=lt&app_language=en')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', 'transportas-ir-kelione-mieste')
            ->assertJsonPath('data.0.title', 'Transport and City Travel')
            ->assertJsonPath('data.0.scenarios_count', 6)
            ->assertJsonPath('data.0.scenarios.0.id', 'bilietas')
            ->assertJsonPath('data.0.scenarios.1.id', 'kur-vaziuojate')
            ->assertJsonPath('data.0.scenarios.2.id', 'kuris-autobusas')
            ->assertJsonPath('data.0.scenarios.3.id', 'kada-isvyksta')
            ->assertJsonPath('data.0.scenarios.4.id', 'taksi')
            ->assertJsonPath('data.0.scenarios.5.id', 'kelione-mieste');

        $this->getJson('/api/v1/scenarios/kelione-mieste?app_language=en')
            ->assertOk()
            ->assertJsonPath('data.unit.id', 'transportas-ir-kelione-mieste')
            ->assertJsonPath('data.note.title', 'Before: City Travel')
            ->assertJsonPath('data.start_scene_id', 'tikslas')
            ->assertJsonFragment(['id' => 'capstone-destination', 'next' => 'bilietas'])
            ->assertJsonFragment(['id' => 'capstone-ticket', 'next' => 'laikas'])
            ->assertJsonFragment(['example' => 'Kada išvyksta autobusas?'])
            ->assertJsonFragment(['trigger_goal_id' => 'capstone-time']);
    }

    public function test_oras_ir_drabuziai_unit_seeded_content_is_listed_in_learning_order(): void
    {
        $this->seed([CharacterSeeder::class, OrasDrabuziaiUnitSeeder::class]);

        $this->getJson('/api/v1/units?language=lt&app_language=en')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', 'oras-ir-drabuziai')
            ->assertJsonPath('data.0.title', 'Weather and Clothes')
            ->assertJsonPath('data.0.scenarios_count', 6)
            ->assertJsonPath('data.0.scenarios.0.id', 'koks-oras')
            ->assertJsonPath('data.0.scenarios.1.id', 'silta-ar-salta')
            ->assertJsonPath('data.0.scenarios.2.id', 'ka-apsirengti')
            ->assertJsonPath('data.0.scenarios.3.id', 'man-reikia-drabuzio')
            ->assertJsonPath('data.0.scenarios.4.id', 'spalva-ir-dydis')
            ->assertJsonPath('data.0.scenarios.5.id', 'oras-ir-apranga');

        $this->getJson('/api/v1/scenarios/oras-ir-apranga?app_language=en')
            ->assertOk()
            ->assertJsonPath('data.unit.id', 'oras-ir-drabuziai')
            ->assertJsonPath('data.note.title', 'Before: Weather and Outfit')
            ->assertJsonPath('data.start_scene_id', 'oras')
            ->assertJsonFragment(['id' => 'capstone-weather', 'next' => 'drabuzis'])
            ->assertJsonFragment(['id' => 'capstone-clothing-need', 'next' => 'pasirinkimas'])
            ->assertJsonFragment(['example' => 'Noriu mėlynos striukės.'])
            ->assertJsonFragment(['trigger_goal_id' => 'capstone-color-size']);
    }

    public function test_sveikata_ir_vaistine_unit_seeded_content_is_listed_in_learning_order(): void
    {
        $this->seed([CharacterSeeder::class, SveikataVaistineUnitSeeder::class]);

        $this->getJson('/api/v1/units?language=lt&app_language=en')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', 'sveikata-ir-vaistine')
            ->assertJsonPath('data.0.title', 'Health and Pharmacy')
            ->assertJsonPath('data.0.scenarios_count', 6)
            ->assertJsonPath('data.0.scenarios.0.id', 'kaip-jauciates')
            ->assertJsonPath('data.0.scenarios.1.id', 'ka-skauda')
            ->assertJsonPath('data.0.scenarios.2.id', 'vaisto-prasymas')
            ->assertJsonPath('data.0.scenarios.3.id', 'kaip-vartoti')
            ->assertJsonPath('data.0.scenarios.4.id', 'mokejimas-vaistineje')
            ->assertJsonPath('data.0.scenarios.5.id', 'vaistineje-pokalbis');

        $this->getJson('/api/v1/scenarios/vaistineje-pokalbis?app_language=en')
            ->assertOk()
            ->assertJsonPath('data.unit.id', 'sveikata-ir-vaistine')
            ->assertJsonPath('data.note.title', 'Before: Pharmacy Conversation')
            ->assertJsonPath('data.start_scene_id', 'problema')
            ->assertJsonFragment(['id' => 'capstone-health-problem', 'next' => 'vaistas'])
            ->assertJsonFragment(['id' => 'capstone-ask-medicine', 'next' => 'vartojimas'])
            ->assertJsonFragment(['example' => 'Kaip vartoti šį vaistą?'])
            ->assertJsonFragment(['trigger_goal_id' => 'capstone-use-or-pay']);
    }

    public function test_klaseje_ir_mokantis_unit_seeded_content_is_listed_in_learning_order(): void
    {
        $this->seed([CharacterSeeder::class, KlasejeMokantisUnitSeeder::class]);

        $this->getJson('/api/v1/units?language=lt&app_language=en')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', 'klaseje-ir-mokantis')
            ->assertJsonPath('data.0.title', 'In Class and Learning')
            ->assertJsonPath('data.0.scenarios_count', 6)
            ->assertJsonPath('data.0.scenarios.0.id', 'pamokoje')
            ->assertJsonPath('data.0.scenarios.1.id', 'as-nesuprantu')
            ->assertJsonPath('data.0.scenarios.2.id', 'pakartokite-prasau')
            ->assertJsonPath('data.0.scenarios.3.id', 'ka-reiskia')
            ->assertJsonPath('data.0.scenarios.4.id', 'ar-galite-padeti')
            ->assertJsonPath('data.0.scenarios.5.id', 'mokymosi-pokalbis');

        $this->getJson('/api/v1/scenarios/mokymosi-pokalbis?app_language=en')
            ->assertOk()
            ->assertJsonPath('data.unit.id', 'klaseje-ir-mokantis')
            ->assertJsonPath('data.note.title', 'Before: Learning Conversation')
            ->assertJsonPath('data.start_scene_id', 'supratimas')
            ->assertJsonFragment(['id' => 'capstone-not-understand', 'next' => 'pakartojimas'])
            ->assertJsonFragment(['id' => 'capstone-repeat', 'next' => 'reiksme'])
            ->assertJsonFragment(['example' => 'Ką reiškia šis žodis?'])
            ->assertJsonFragment(['trigger_goal_id' => 'capstone-meaning']);
    }

    public function test_laisvalaikis_ir_pomegiai_unit_seeded_content_is_listed_in_learning_order(): void
    {
        $this->seed([CharacterSeeder::class, LaisvalaikisPomegiaiUnitSeeder::class]);

        $this->getJson('/api/v1/units?language=lt&app_language=en')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', 'laisvalaikis-ir-pomegiai')
            ->assertJsonPath('data.0.title', 'Free Time and Hobbies')
            ->assertJsonPath('data.0.scenarios_count', 6)
            ->assertJsonPath('data.0.scenarios.0.id', 'ka-veikiate-laisvalaikiu')
            ->assertJsonPath('data.0.scenarios.1.id', 'man-patinka')
            ->assertJsonPath('data.0.scenarios.2.id', 'man-nepatinka')
            ->assertJsonPath('data.0.scenarios.3.id', 'sportas-ar-muzika')
            ->assertJsonPath('data.0.scenarios.4.id', 'kada-turite-laiko')
            ->assertJsonPath('data.0.scenarios.5.id', 'laisvalaikio-pokalbis');

        $this->getJson('/api/v1/scenarios/laisvalaikio-pokalbis?app_language=en')
            ->assertOk()
            ->assertJsonPath('data.unit.id', 'laisvalaikis-ir-pomegiai')
            ->assertJsonPath('data.note.title', 'Before: Free-Time Conversation')
            ->assertJsonPath('data.start_scene_id', 'veikla')
            ->assertJsonFragment(['id' => 'capstone-free-time-activity', 'next' => 'patinka'])
            ->assertJsonFragment(['id' => 'capstone-like', 'next' => 'laikas'])
            ->assertJsonFragment(['example' => 'Turiu laiko vakare.'])
            ->assertJsonFragment(['trigger_goal_id' => 'capstone-free-time']);
    }

    public function test_planai_ir_kvietimai_unit_seeded_content_is_listed_in_learning_order(): void
    {
        $this->seed([CharacterSeeder::class, PlanaiKvietimaiUnitSeeder::class]);

        $this->getJson('/api/v1/units?language=lt&app_language=en')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', 'planai-ir-kvietimai')
            ->assertJsonPath('data.0.title', 'Plans and Invitations')
            ->assertJsonPath('data.0.scenarios_count', 6)
            ->assertJsonPath('data.0.scenarios.0.id', 'ar-nori-eiti')
            ->assertJsonPath('data.0.scenarios.1.id', 'taip-arba-ne')
            ->assertJsonPath('data.0.scenarios.2.id', 'kada-susitinkame')
            ->assertJsonPath('data.0.scenarios.3.id', 'kur-susitinkame')
            ->assertJsonPath('data.0.scenarios.4.id', 'atsiprasau-negaliu')
            ->assertJsonPath('data.0.scenarios.5.id', 'susitikimo-planas');

        $this->getJson('/api/v1/scenarios/susitikimo-planas?app_language=en')
            ->assertOk()
            ->assertJsonPath('data.unit.id', 'planai-ir-kvietimai')
            ->assertJsonPath('data.note.title', 'Before: Meeting Plan')
            ->assertJsonPath('data.start_scene_id', 'kvietimas')
            ->assertJsonFragment(['id' => 'capstone-invite', 'next' => 'atsakymas'])
            ->assertJsonFragment(['id' => 'capstone-answer', 'next' => 'laikas'])
            ->assertJsonFragment(['id' => 'capstone-meeting-time', 'next' => 'vieta'])
            ->assertJsonFragment(['example' => 'Susitinkame prie kavinės.'])
            ->assertJsonFragment(['trigger_goal_id' => 'capstone-meeting-place']);
    }

    public function test_gebejimai_ir_poreikiai_unit_seeded_content_is_listed_in_learning_order(): void
    {
        $this->seed([CharacterSeeder::class, GebejimaiPoreikiaiUnitSeeder::class]);

        $this->getJson('/api/v1/units?language=lt&app_language=en')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', 'gebejimai-ir-poreikiai')
            ->assertJsonPath('data.0.title', 'Abilities and Needs')
            ->assertJsonPath('data.0.scenarios_count', 6)
            ->assertJsonPath('data.0.scenarios.0.id', 'as-galiu')
            ->assertJsonPath('data.0.scenarios.1.id', 'as-negaliu')
            ->assertJsonPath('data.0.scenarios.2.id', 'as-noriu')
            ->assertJsonPath('data.0.scenarios.3.id', 'man-reikia')
            ->assertJsonPath('data.0.scenarios.4.id', 'as-turiu')
            ->assertJsonPath('data.0.scenarios.5.id', 'poreikio-pokalbis');

        $this->getJson('/api/v1/scenarios/poreikio-pokalbis?app_language=en')
            ->assertOk()
            ->assertJsonPath('data.unit.id', 'gebejimai-ir-poreikiai')
            ->assertJsonPath('data.note.title', 'Before: Needs Conversation')
            ->assertJsonPath('data.start_scene_id', 'poreikis')
            ->assertJsonFragment(['id' => 'capstone-need', 'next' => 'gebejimas'])
            ->assertJsonFragment(['id' => 'capstone-can', 'next' => 'ribojimas'])
            ->assertJsonFragment(['example' => 'Aš negaliu ateiti šiandien.'])
            ->assertJsonFragment(['trigger_goal_id' => 'capstone-limitation']);
    }

    public function test_kelione_ir_apgyvendinimas_unit_seeded_content_is_listed_in_learning_order(): void
    {
        $this->seed([CharacterSeeder::class, KelioneApgyvendinimasUnitSeeder::class]);

        $this->getJson('/api/v1/units?language=lt&app_language=en')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', 'kelione-ir-apgyvendinimas')
            ->assertJsonPath('data.0.title', 'Travel and Accommodation')
            ->assertJsonPath('data.0.scenarios_count', 6)
            ->assertJsonPath('data.0.scenarios.0.id', 'viesbutyje-registracija')
            ->assertJsonPath('data.0.scenarios.1.id', 'vardas-registracijai')
            ->assertJsonPath('data.0.scenarios.2.id', 'kambarys')
            ->assertJsonPath('data.0.scenarios.3.id', 'raktas-ir-numeris')
            ->assertJsonPath('data.0.scenarios.4.id', 'problema-kambaryje')
            ->assertJsonPath('data.0.scenarios.5.id', 'atvykimas-i-viesbuti');

        $this->getJson('/api/v1/scenarios/atvykimas-i-viesbuti?app_language=en')
            ->assertOk()
            ->assertJsonPath('data.unit.id', 'kelione-ir-apgyvendinimas')
            ->assertJsonPath('data.note.title', 'Before: Hotel Arrival')
            ->assertJsonPath('data.start_scene_id', 'registracija')
            ->assertJsonFragment(['id' => 'capstone-reservation', 'next' => 'vardas'])
            ->assertJsonFragment(['id' => 'capstone-checkin-name', 'next' => 'kambarys'])
            ->assertJsonFragment(['id' => 'capstone-room', 'next' => 'raktas'])
            ->assertJsonFragment(['example' => 'Koks mano kambario numeris?'])
            ->assertJsonFragment(['trigger_goal_id' => 'capstone-key-number']);
    }

    public function test_a1_review_capstone_unit_seeded_content_is_listed_in_learning_order(): void
    {
        $this->seed([CharacterSeeder::class, A1ReviewCapstoneUnitSeeder::class]);

        $this->getJson('/api/v1/units?language=lt&app_language=en')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', 'a1-kartojimas')
            ->assertJsonPath('data.0.title', 'A1 Review')
            ->assertJsonPath('data.0.scenarios_count', 6)
            ->assertJsonPath('data.0.scenarios.0.id', 'a1-apie-save')
            ->assertJsonPath('data.0.scenarios.1.id', 'a1-kasdieniai-poreikiai')
            ->assertJsonPath('data.0.scenarios.2.id', 'a1-mieste')
            ->assertJsonPath('data.0.scenarios.3.id', 'a1-paslaugos')
            ->assertJsonPath('data.0.scenarios.4.id', 'a1-planai-ir-laikas')
            ->assertJsonPath('data.0.scenarios.5.id', 'a1-baigiamasis-pokalbis');

        $this->getJson('/api/v1/scenarios/a1-baigiamasis-pokalbis?app_language=en')
            ->assertOk()
            ->assertJsonPath('data.unit.id', 'a1-kartojimas')
            ->assertJsonPath('data.note.title', 'Before: Final A1 Conversation')
            ->assertJsonPath('data.start_scene_id', 'pradzia')
            ->assertJsonFragment(['id' => 'final-introduction', 'next' => 'miestas'])
            ->assertJsonFragment(['id' => 'final-city-help', 'next' => 'paslauga'])
            ->assertJsonFragment(['id' => 'final-service-request', 'next' => 'planas'])
            ->assertJsonFragment(['id' => 'final-plan', 'next' => 'pabaiga'])
            ->assertJsonFragment(['example' => 'Ačiū, viso gero.'])
            ->assertJsonFragment(['trigger_goal_id' => 'final-goodbye']);
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

    public function test_current_seed_chain_archives_legacy_and_non_unit_content(): void
    {
        $this->seed([
            CharacterSeeder::class,
            A1ScenarioSeeder::class,
            RestaurantScenarioSeeder::class,
            PharmacyVisitScenarioSeeder::class,
            UnitsSeeder::class,
        ]);

        $this->assertDatabaseHas('units', [
            'slug' => 'a1-practice-library',
            'status' => ContentStatus::Archived->value,
        ]);
        $this->assertDatabaseHas('units', [
            'slug' => 'sveikata',
            'status' => ContentStatus::Archived->value,
        ]);
        $this->assertDatabaseHas('scenarios', [
            'slug' => 'viesbutyje',
            'status' => ContentStatus::Archived->value,
        ]);
        $this->assertDatabaseHas('scenarios', [
            'slug' => 'pharmacy-visit',
            'status' => ContentStatus::Archived->value,
        ]);
        $this->assertSame(0, Scenario::query()->published()->whereNull('unit_id')->count());

        $this->getJson('/api/v1/units?language=lt&app_language=en')
            ->assertOk()
            ->assertJsonMissing(['id' => 'a1-practice-library'])
            ->assertJsonMissing(['id' => 'viesbutyje'])
            ->assertJsonMissing(['id' => 'sveikata'])
            ->assertJsonFragment(['id' => 'susipazinkime'])
            ->assertJsonFragment(['id' => 'maistas-ir-gerimai'])
            ->assertJsonFragment(['id' => 'parduotuveje']);

        $this->getJson('/api/v1/scenarios/viesbutyje')
            ->assertNotFound();
    }

    public function test_draft_scenarios_return_404_from_the_learner_api(): void
    {
        $scenario = Scenario::factory()->draft()->create(['slug' => 'not-ready']);

        $response = $this->getJson("/api/v1/scenarios/{$scenario->slug}");

        $response->assertNotFound();
    }
}
