<?php

namespace App\Http\Controllers\Cms;

use App\CefrLevel;
use App\ContentStatus;
use App\Http\Controllers\Controller;
use App\Models\Character;
use App\Models\Language;
use App\Models\Scenario;
use App\Services\Content\AuditScenarioContent;
use App\Services\Content\SyncContentTranslations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ScenarioController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Scenarios/Index', [
            'scenarios' => Scenario::query()
                ->with(['character', 'language', 'translations'])
                ->withCount('scenes')
                ->orderBy('sort_order')
                ->orderBy('title')
                ->get()
                ->map(fn (Scenario $scenario): array => $this->summary($scenario)),
            'characters' => $this->characterOptions(),
            'languages' => $this->languageOptions(),
            'statuses' => array_column(ContentStatus::cases(), 'value'),
            'levels' => array_column(CefrLevel::cases(), 'value'),
        ]);
    }

    public function store(Request $request, SyncContentTranslations $translations): RedirectResponse
    {
        $data = $this->validated($request, translations: $translations);
        $scenario = Scenario::query()->create($this->contentData($data));
        $translations->sync($scenario, $data['translations'] ?? [], ['title', 'subtitle', 'description']);

        return redirect()->route('cms.scenarios.show', $scenario)->with('success', 'Scenario created.');
    }

    public function show(Scenario $scenario, AuditScenarioContent $auditor): Response
    {
        $scenario->load([
            'character',
            'language',
            'translations',
            'note.translations',
            'scenes.translations',
            'scenes.npcLines.translations',
            'scenes.npcLines.triggerGoal',
            'scenes.goals.translations',
            'scenes.goals.nextScene',
            'scenes.goals.responseLines.translations',
            'scenes.goals.responseLines.triggerGoal',
            'scenes.props.translations',
        ]);

        return Inertia::render('Scenarios/Show', [
            'scenario' => $this->detail($scenario),
            'auditIssues' => $auditor->issues($scenario, publishedOnly: false),
            'characters' => $this->characterOptions(),
            'languages' => $this->languageOptions(),
            'statuses' => array_column(ContentStatus::cases(), 'value'),
            'levels' => array_column(CefrLevel::cases(), 'value'),
        ]);
    }

    public function update(Request $request, Scenario $scenario, SyncContentTranslations $translations): RedirectResponse
    {
        $data = $this->validated($request, $scenario, $translations);
        $scenario->update($this->contentData($data));
        $translations->sync($scenario, $data['translations'] ?? [], ['title', 'subtitle', 'description']);

        return back()->with('success', 'Scenario updated.');
    }

    private function validated(Request $request, ?Scenario $scenario = null, ?SyncContentTranslations $translations = null): array
    {
        return $request->validate([
            'language_id' => ['required', 'integer', 'exists:languages,id'],
            'character_id' => ['required', 'integer', 'exists:characters,id'],
            'slug' => ['required', 'string', 'max:100', Rule::unique('scenarios', 'slug')->ignore($scenario)],
            'title' => ['required', 'string', 'max:160'],
            'subtitle' => ['required', 'string', 'max:160'],
            'description' => ['required', 'string', 'max:1000'],
            'emoji' => ['required', 'string', 'max:20'],
            'tone' => ['required', Rule::in(['primary', 'amber', 'berry', 'sky', 'mint'])],
            'cefr_level' => ['nullable', Rule::enum(CefrLevel::class)],
            'start_scene_slug' => ['nullable', 'string', 'max:100'],
            'status' => ['required', Rule::enum(ContentStatus::class)],
            'is_free' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
            ...($translations?->rules(['title', 'subtitle', 'description']) ?? []),
        ]);
    }

    private function contentData(array $data): array
    {
        unset($data['translations']);

        return $data;
    }

    private function summary(Scenario $scenario): array
    {
        return [
            'id' => $scenario->id,
            'slug' => $scenario->slug,
            'title' => $scenario->title,
            'subtitle' => $scenario->subtitle,
            'description' => $scenario->description,
            'emoji' => $scenario->emoji,
            'tone' => $scenario->tone,
            'cefr_level' => $scenario->cefr_level?->value,
            'start_scene_slug' => $scenario->start_scene_slug,
            'status' => $scenario->status->value,
            'is_free' => (bool) $scenario->is_free,
            'sort_order' => $scenario->sort_order,
            'language_id' => $scenario->language_id,
            'language' => $scenario->language ? [
                'id' => $scenario->language->id,
                'code' => $scenario->language->code,
                'name' => $scenario->language->name,
                'native_name' => $scenario->language->native_name,
            ] : null,
            'character_id' => $scenario->character_id,
            'character' => $scenario->character?->name,
            'scenes_count' => $scenario->scenes_count ?? null,
            'updated_at' => $scenario->updated_at?->toDateTimeString(),
            'translations' => $scenario->translationMap(),
        ];
    }

    private function detail(Scenario $scenario): array
    {
        return [
            ...$this->summary($scenario),
            'note' => $scenario->note ? [
                'id' => $scenario->note->id,
                'title' => $scenario->note->title,
                'body' => $scenario->note->body,
                'cefr_level' => $scenario->note->cefr_level?->value,
                'estimated_minutes' => $scenario->note->estimated_minutes,
                'status' => $scenario->note->status->value,
                'translations' => $scenario->note->translationMap(),
            ] : null,
            'scenes' => $scenario->scenes->map(fn ($scene): array => [
                'id' => $scene->id,
                'slug' => $scene->slug,
                'title' => $scene->title,
                'setting' => $scene->setting,
                'cefr_level' => $scene->cefr_level?->value,
                'sort_order' => $scene->sort_order,
                'translations' => $scene->translationMap(),
                'lines' => $scene->npcLines->map(fn ($line): array => [
                    'id' => $line->id,
                    'target_text' => $line->target_text,
                    'support_translation' => $line->support_translation,
                    'cefr_level' => $line->cefr_level?->value,
                    'trigger_goal_id' => $line->triggerGoal?->slug,
                    'trigger_goal_db_id' => $line->trigger_goal_id,
                    'priority' => $line->priority,
                    'sort_order' => $line->sort_order,
                    'translations' => $line->translationMap(),
                ]),
                'goals' => $scene->goals->map(fn ($goal): array => [
                    'id' => $goal->id,
                    'slug' => $goal->slug,
                    'label' => $goal->label,
                    'intent' => $goal->intent,
                    'example' => $goal->example,
                    'cefr_level' => $goal->cefr_level?->value,
                    'next_scene_id' => $goal->next_scene_id,
                    'next_scene_slug' => $goal->nextScene?->slug,
                    'sort_order' => $goal->sort_order,
                    'translations' => $goal->translationMap(),
                    'response_lines' => $goal->responseLines->map(fn ($line): array => [
                        'id' => $line->id,
                        'target_text' => $line->target_text,
                        'support_translation' => $line->support_translation,
                        'cefr_level' => $line->cefr_level?->value,
                        'trigger_goal_id' => $line->triggerGoal?->slug,
                        'trigger_goal_db_id' => $line->trigger_goal_id,
                        'priority' => $line->priority,
                        'sort_order' => $line->sort_order,
                        'translations' => $line->translationMap(),
                    ]),
                ]),
                'props' => $scene->props->map(fn ($prop): array => [
                    'id' => $prop->id,
                    'type' => $prop->type,
                    'target_text' => $prop->target_text,
                    'support_translation' => $prop->support_translation,
                    'price' => $prop->price,
                    'metadata' => $prop->metadata,
                    'sort_order' => $prop->sort_order,
                    'translations' => $prop->translationMap(),
                ]),
            ]),
        ];
    }

    private function characterOptions(): array
    {
        return Character::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'language_id', 'name', 'slug'])
            ->map(fn (Character $character): array => [
                'id' => $character->id,
                'language_id' => $character->language_id,
                'name' => $character->name,
                'slug' => $character->slug,
            ])
            ->all();
    }

    private function languageOptions(): array
    {
        return Language::query()
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'native_name', 'support_language_code', 'support_language_name'])
            ->map(fn (Language $language): array => [
                'id' => $language->id,
                'code' => $language->code,
                'name' => $language->name,
                'native_name' => $language->native_name,
                'support_language_code' => $language->support_language_code,
                'support_language_name' => $language->support_language_name,
            ])
            ->all();
    }
}
