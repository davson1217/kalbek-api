<?php

namespace App\Http\Controllers\Cms;

use App\CefrLevel;
use App\ContentStatus;
use App\Http\Controllers\Controller;
use App\Models\Character;
use App\Models\Scenario;
use App\Services\Content\AuditScenarioContent;
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
                ->with('character')
                ->withCount('scenes')
                ->orderBy('sort_order')
                ->orderBy('title')
                ->get()
                ->map(fn (Scenario $scenario): array => $this->summary($scenario)),
            'characters' => $this->characterOptions(),
            'statuses' => array_column(ContentStatus::cases(), 'value'),
            'levels' => array_column(CefrLevel::cases(), 'value'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $scenario = Scenario::query()->create($this->validated($request));

        return redirect()->route('cms.scenarios.show', $scenario)->with('success', 'Scenario created.');
    }

    public function show(Scenario $scenario, AuditScenarioContent $auditor): Response
    {
        $scenario->load([
            'character',
            'scenes.npcLines.triggerGoal',
            'scenes.goals.nextScene',
            'scenes.goals.responseLines.triggerGoal',
            'scenes.props',
        ]);

        return Inertia::render('Scenarios/Show', [
            'scenario' => $this->detail($scenario),
            'auditIssues' => $auditor->issues($scenario, publishedOnly: false),
            'characters' => $this->characterOptions(),
            'statuses' => array_column(ContentStatus::cases(), 'value'),
            'levels' => array_column(CefrLevel::cases(), 'value'),
        ]);
    }

    public function update(Request $request, Scenario $scenario): RedirectResponse
    {
        $scenario->update($this->validated($request, $scenario));

        return back()->with('success', 'Scenario updated.');
    }

    private function validated(Request $request, ?Scenario $scenario = null): array
    {
        return $request->validate([
            'character_id' => ['required', 'integer', 'exists:characters,id'],
            'slug' => ['required', 'string', 'max:100', Rule::unique('scenarios', 'slug')->ignore($scenario)],
            'title' => ['required', 'string', 'max:160'],
            'subtitle' => ['required', 'string', 'max:160'],
            'description' => ['required', 'string', 'max:1000'],
            'emoji' => ['required', 'string', 'max:20'],
            'tone' => ['required', Rule::in(['primary', 'amber', 'berry', 'sky'])],
            'cefr_level' => ['nullable', Rule::enum(CefrLevel::class)],
            'start_scene_slug' => ['nullable', 'string', 'max:100'],
            'status' => ['required', Rule::enum(ContentStatus::class)],
            'sort_order' => ['required', 'integer', 'min:0'],
        ]);
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
            'sort_order' => $scenario->sort_order,
            'character_id' => $scenario->character_id,
            'character' => $scenario->character?->name,
            'scenes_count' => $scenario->scenes_count ?? null,
            'updated_at' => $scenario->updated_at?->toDateTimeString(),
        ];
    }

    private function detail(Scenario $scenario): array
    {
        return [
            ...$this->summary($scenario),
            'scenes' => $scenario->scenes->map(fn ($scene): array => [
                'id' => $scene->id,
                'slug' => $scene->slug,
                'setting' => $scene->setting,
                'cefr_level' => $scene->cefr_level?->value,
                'sort_order' => $scene->sort_order,
                'lines' => $scene->npcLines->map(fn ($line): array => [
                    'id' => $line->id,
                    'lt' => $line->lt,
                    'en' => $line->en,
                    'cefr_level' => $line->cefr_level?->value,
                    'trigger_goal_id' => $line->triggerGoal?->slug,
                    'trigger_goal_db_id' => $line->trigger_goal_id,
                    'priority' => $line->priority,
                    'sort_order' => $line->sort_order,
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
                    'response_lines' => $goal->responseLines->map(fn ($line): array => [
                        'id' => $line->id,
                        'lt' => $line->lt,
                        'en' => $line->en,
                        'cefr_level' => $line->cefr_level?->value,
                        'trigger_goal_id' => $line->triggerGoal?->slug,
                        'trigger_goal_db_id' => $line->trigger_goal_id,
                        'priority' => $line->priority,
                        'sort_order' => $line->sort_order,
                    ]),
                ]),
                'props' => $scene->props->map(fn ($prop): array => [
                    'id' => $prop->id,
                    'type' => $prop->type,
                    'lt' => $prop->lt,
                    'en' => $prop->en,
                    'price' => $prop->price,
                    'metadata' => $prop->metadata,
                    'sort_order' => $prop->sort_order,
                ]),
            ]),
        ];
    }

    private function characterOptions(): array
    {
        return Character::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->map(fn (Character $character): array => [
                'id' => $character->id,
                'name' => $character->name,
                'slug' => $character->slug,
            ])
            ->all();
    }
}
