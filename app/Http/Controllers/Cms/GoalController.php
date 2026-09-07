<?php

namespace App\Http\Controllers\Cms;

use App\CefrLevel;
use App\Http\Controllers\Controller;
use App\Models\Goal;
use App\Models\Scenario;
use App\Models\Scene;
use App\Services\Content\SyncContentTranslations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GoalController extends Controller
{
    public function store(Request $request, Scenario $scenario, Scene $scene, SyncContentTranslations $translations): RedirectResponse
    {
        $this->ensureScene($scenario, $scene);
        $data = $this->validated($request, $scenario, $scene, translations: $translations);
        $goal = $scene->goals()->create($this->contentData($data));
        $translations->sync($goal, $data['translations'] ?? [], ['label', 'intent']);

        return back()->with('success', 'Goal created.');
    }

    public function update(Request $request, Scenario $scenario, Scene $scene, Goal $goal, SyncContentTranslations $translations): RedirectResponse
    {
        $this->ensureGoal($scenario, $scene, $goal);
        $data = $this->validated($request, $scenario, $scene, $goal, $translations);
        $goal->update($this->contentData($data));
        $translations->sync($goal, $data['translations'] ?? [], ['label', 'intent']);

        return back()->with('success', 'Goal updated.');
    }

    public function destroy(Scenario $scenario, Scene $scene, Goal $goal): RedirectResponse
    {
        $this->ensureGoal($scenario, $scene, $goal);
        $goal->delete();

        return back()->with('success', 'Goal deleted.');
    }

    private function validated(Request $request, Scenario $scenario, Scene $scene, ?Goal $goal = null, ?SyncContentTranslations $translations = null): array
    {
        return $request->validate([
            'slug' => [
                'required',
                'string',
                'max:100',
                Rule::unique('goals', 'slug')->where('scene_id', $scene->id)->ignore($goal),
            ],
            'label' => ['required', 'string', 'max:180'],
            'intent' => ['required', 'string', 'max:1000'],
            'example' => ['required', 'string', 'max:500'],
            'cefr_level' => ['nullable', Rule::enum(CefrLevel::class)],
            'next_scene_id' => [
                'nullable',
                Rule::exists('scenes', 'id')->where('scenario_id', $scenario->id),
            ],
            'sort_order' => ['required', 'integer', 'min:0'],
            ...($translations?->rules(['label', 'intent']) ?? []),
        ]);
    }

    private function contentData(array $data): array
    {
        unset($data['translations']);

        return $data;
    }

    private function ensureScene(Scenario $scenario, Scene $scene): void
    {
        abort_unless($scene->scenario_id === $scenario->id, 404);
    }

    private function ensureGoal(Scenario $scenario, Scene $scene, Goal $goal): void
    {
        $this->ensureScene($scenario, $scene);
        abort_unless($goal->scene_id === $scene->id, 404);
    }
}
