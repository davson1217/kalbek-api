<?php

namespace Tests\Feature;

use App\Models\Goal;
use App\Models\Language;
use App\Models\NpcLine;
use App\Models\Scenario;
use App\Models\ScenarioNote;
use App\Models\Scene;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\CharacterSeeder;
use Database\Seeders\RestaurantScenarioSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CmsContentManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            ValidateCsrfToken::class,
            VerifyCsrfToken::class,
        ]);
        $this->withSession(['_token' => 'test-token']);
        $this->withHeader('X-CSRF-TOKEN', 'test-token');
    }

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

    public function test_admin_can_view_character_voice_options(): void
    {
        $this->seed([CharacterSeeder::class, RestaurantScenarioSeeder::class]);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('cms.characters.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Characters/Index')
                ->has('characters', 3)
                ->has('ttsVoiceOptions')
                ->where('ttsVoiceOptions.0.value', 'Zephyr'));
    }

    public function test_admin_can_manage_languages(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('cms.languages.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Languages/Index'));

        $this->actingAs($admin)->post(route('cms.languages.store'), [
            'code' => 'en',
            'name' => 'English',
            'native_name' => 'English',
            'support_language_code' => 'en',
            'support_language_name' => 'English',
            'status' => 'active',
            'default_voice' => 'default-female',
            'sort_order' => 20,
        ])->assertRedirect();

        $language = Language::query()->where('code', 'en')->firstOrFail();

        $this->actingAs($admin)->put(route('cms.languages.update', $language), [
            'code' => 'en',
            'name' => 'English',
            'native_name' => 'English',
            'support_language_code' => 'en',
            'support_language_name' => 'English',
            'status' => 'draft',
            'default_voice' => 'default-male',
            'sort_order' => 30,
        ])->assertRedirect();

        $this->assertDatabaseHas('languages', [
            'code' => 'en',
            'support_language_code' => 'en',
            'support_language_name' => 'English',
            'status' => 'draft',
            'default_voice' => 'default-male',
            'sort_order' => 30,
        ]);
    }

    public function test_admin_can_manage_units_and_assign_scenarios(): void
    {
        $this->seed([CharacterSeeder::class, RestaurantScenarioSeeder::class]);
        $admin = User::factory()->create(['role' => 'admin']);
        $language = Language::query()->where('code', 'lt')->firstOrFail();
        $scenario = Scenario::query()->where('slug', 'restoranas')->firstOrFail();

        $this->actingAs($admin)->get(route('cms.units.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Units/Index')
                ->has('units', 0));

        $this->actingAs($admin)->post(route('cms.units.store'), [
            'language_id' => $language->id,
            'slug' => 'susipazinkime',
            'title' => 'Susipažinkime',
            'description' => 'A first unit for greetings and simple introductions.',
            'cefr_level' => 'a1',
            'status' => 'published',
            'sort_order' => 10,
            'translations' => [
                'title' => ['en' => 'Let’s Get Acquainted'],
                'description' => ['en' => 'A first unit for greetings and simple introductions.'],
            ],
        ])->assertRedirect();

        $unit = Unit::query()->where('slug', 'susipazinkime')->firstOrFail();

        $this->actingAs($admin)->put(route('cms.scenarios.update', $scenario), [
            'language_id' => $scenario->language_id,
            'unit_id' => $unit->id,
            'character_id' => $scenario->character_id,
            'slug' => $scenario->slug,
            'title' => $scenario->title,
            'subtitle' => $scenario->subtitle,
            'description' => $scenario->description,
            'emoji' => $scenario->emoji,
            'tone' => $scenario->tone,
            'cefr_level' => $scenario->cefr_level?->value,
            'start_scene_slug' => $scenario->start_scene_slug,
            'status' => $scenario->status->value,
            'is_free' => $scenario->is_free,
            'sort_order' => $scenario->sort_order,
        ])->assertRedirect();

        $this->assertDatabaseHas('units', [
            'slug' => 'susipazinkime',
            'title' => 'Susipažinkime',
            'status' => 'published',
        ]);
        $this->assertDatabaseHas('scenarios', [
            'slug' => 'restoranas',
            'unit_id' => $unit->id,
        ]);
        $this->assertDatabaseHas('content_translations', [
            'translatable_type' => Unit::class,
            'translatable_id' => $unit->id,
            'field' => 'title',
            'locale' => 'en',
            'value' => 'Let’s Get Acquainted',
        ]);

        $this->actingAs($admin)->get(route('cms.scenarios.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Scenarios/Index')
                ->has('units', 1)
                ->where('scenarios.0.unit.title', 'Susipažinkime'));
    }

    public function test_admin_can_create_scene_goal_and_npc_line(): void
    {
        $this->seed([CharacterSeeder::class, RestaurantScenarioSeeder::class]);
        $admin = User::factory()->create(['role' => 'admin']);
        $scenario = Scenario::query()->where('slug', 'restoranas')->firstOrFail();

        $this->actingAs($admin)->post(route('cms.scenarios.scenes.store', $scenario), [
            'slug' => 'test-scene',
            'title' => 'Test scene',
            'setting' => 'A teacher-authored test scene.',
            'cefr_level' => 'a1',
            'sort_order' => 999,
            'translations' => [
                'title' => ['lt' => 'Bandomoji scena'],
                'setting' => ['lt' => 'Mokytojo sukurta bandomoji scena.'],
            ],
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
            'translations' => [
                'label' => ['lt' => 'Paklauskite bandomo klausimo'],
                'intent' => ['lt' => 'Paklausti trumpo mokytojo sukurto bandomojo klausimo.'],
            ],
        ])->assertRedirect();

        $goal = Goal::query()->where('scene_id', $scene->id)->where('slug', 'test-goal')->firstOrFail();

        $this->actingAs($admin)->post(route('cms.scenarios.scenes.lines.store', [$scenario, $scene]), [
            'target_text' => 'Taip, turime testą.',
            'support_translation' => 'Yes, we have a test.',
            'cefr_level' => 'a1',
            'trigger_goal_id' => $goal->id,
            'priority' => 100,
            'sort_order' => 10,
            'translations' => [
                'support_translation' => ['lt' => 'Taip, turime testą.'],
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('npc_lines', [
            'scene_id' => $scene->id,
            'trigger_goal_id' => $goal->id,
            'target_text' => 'Taip, turime testą.',
            'cefr_level' => 'a1',
            'priority' => 100,
        ]);
        $this->assertDatabaseHas('content_translations', [
            'translatable_type' => Scene::class,
            'translatable_id' => $scene->id,
            'field' => 'title',
            'locale' => 'lt',
            'value' => 'Bandomoji scena',
        ]);
        $this->assertDatabaseHas('content_translations', [
            'translatable_type' => Goal::class,
            'translatable_id' => $goal->id,
            'field' => 'label',
            'locale' => 'lt',
            'value' => 'Paklauskite bandomo klausimo',
        ]);

        $this->actingAs($admin)->get(route('cms.scenarios.show', $scenario))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Scenarios/Show')
                ->where('scenario.scenes.5.title', 'Test scene')
                ->where('scenario.scenes.5.translations.title.lt', 'Bandomoji scena')
                ->where('scenario.scenes.5.goals.0.response_lines.0.target_text', 'Taip, turime testą.'));
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
            'target_text' => 'Taip.',
            'support_translation' => 'Yes.',
            'cefr_level' => 'a1',
            'trigger_goal_id' => $otherGoal->id,
            'priority' => 100,
            'sort_order' => 10,
        ])
            ->assertRedirect(route('cms.scenarios.show', $scenario))
            ->assertSessionHasErrors('trigger_goal_id');

        $this->assertSame(0, NpcLine::query()->where('scene_id', $firstScene->id)->where('target_text', 'Taip.')->count());
    }

    public function test_admin_can_manage_a_scenario_preparation_note(): void
    {
        $this->seed([CharacterSeeder::class, RestaurantScenarioSeeder::class]);
        $admin = User::factory()->create(['role' => 'admin']);
        $language = Language::query()->where('code', 'lt')->firstOrFail();
        $character = Scenario::query()->where('slug', 'restoranas')->firstOrFail()->character;
        $scenario = Scenario::factory()->create([
            'language_id' => $language->id,
            'character_id' => $character->id,
            'slug' => 'note-test',
            'title' => 'Note test',
            'status' => 'published',
        ]);

        $this->actingAs($admin)->post(route('cms.scenarios.note.store', $scenario), [
            'title' => 'Before the restaurant',
            'body' => 'Practise greeting the waitress and asking for a table.',
            'cefr_level' => 'a1',
            'estimated_minutes' => 2,
            'status' => 'published',
            'translations' => [
                'title' => ['lt' => 'Prieš restoraną'],
                'body' => ['lt' => 'Pasimokykite pasisveikinti ir paprašyti staliuko.'],
            ],
        ])->assertRedirect();

        $note = ScenarioNote::query()->where('scenario_id', $scenario->id)->firstOrFail();

        $this->assertDatabaseHas('scenario_notes', [
            'scenario_id' => $scenario->id,
            'title' => 'Before the restaurant',
            'status' => 'published',
        ]);
        $this->assertDatabaseHas('content_translations', [
            'translatable_type' => ScenarioNote::class,
            'translatable_id' => $note->id,
            'field' => 'body',
            'locale' => 'lt',
            'value' => 'Pasimokykite pasisveikinti ir paprašyti staliuko.',
        ]);

        $this->actingAs($admin)->put(route('cms.scenarios.note.update', [$scenario, $note]), [
            'title' => 'Restaurant prep',
            'body' => 'Practise ordering politely.',
            'cefr_level' => 'a1',
            'estimated_minutes' => 3,
            'status' => 'draft',
        ])->assertRedirect();

        $this->assertDatabaseHas('scenario_notes', [
            'id' => $note->id,
            'title' => 'Restaurant prep',
            'estimated_minutes' => 3,
            'status' => 'draft',
        ]);

        $this->actingAs($admin)->get(route('cms.scenarios.show', $scenario))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Scenarios/Show')
                ->where('scenario.note.title', 'Restaurant prep')
                ->where('scenario.note.estimated_minutes', 3));
    }

    public function test_admin_can_see_scenario_flow_audit_issues(): void
    {
        $this->seed([CharacterSeeder::class, RestaurantScenarioSeeder::class]);
        $admin = User::factory()->create(['role' => 'admin']);
        $scenario = Scenario::query()->where('slug', 'restoranas')->firstOrFail();

        $scene = Scene::factory()->for($scenario)->create([
            'slug' => 'unfinished-scene',
            'setting' => 'An unfinished teacher-authored scene.',
        ]);

        Goal::factory()->for($scene)->create([
            'slug' => 'unfinished-goal',
            'label' => 'Ask an unfinished question',
            'intent' => 'The learner asks an unfinished question.',
            'example' => 'Ar turite?',
        ]);

        $this->actingAs($admin)->get(route('cms.scenarios.show', $scenario))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Scenarios/Show')
                ->where('auditIssues', fn ($issues): bool => collect($issues)->contains(fn (array $issue): bool => $issue['severity'] === 'warning'
                    && $issue['category'] === 'structure'
                    && $issue['scene_slug'] === 'unfinished-scene'
                    && $issue['message'] === 'Scene [restoranas/unfinished-scene] is not reachable from the start scene [atvykimas].')
                    && collect($issues)->contains(fn (array $issue): bool => $issue['severity'] === 'critical'
                        && $issue['category'] === 'dialogue'
                        && $issue['message'] === 'Scene [restoranas/unfinished-scene] has no opening/generic line.')
                    && collect($issues)->contains(fn (array $issue): bool => ($issue['goal_slug'] ?? null) === 'unfinished-goal'
                        && filled($issue['recommendation'] ?? null))));
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
