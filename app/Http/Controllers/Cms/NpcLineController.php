<?php

namespace App\Http\Controllers\Cms;

use App\CefrLevel;
use App\Http\Controllers\Controller;
use App\Models\NpcLine;
use App\Models\Scenario;
use App\Models\Scene;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NpcLineController extends Controller
{
    public function store(Request $request, Scenario $scenario, Scene $scene): RedirectResponse
    {
        $this->ensureScene($scenario, $scene);
        $scene->npcLines()->create($this->validated($request, $scene));

        return back()->with('success', 'NPC line created.');
    }

    public function update(Request $request, Scenario $scenario, Scene $scene, NpcLine $line): RedirectResponse
    {
        $this->ensureLine($scenario, $scene, $line);
        $line->update($this->validated($request, $scene));

        return back()->with('success', 'NPC line updated.');
    }

    public function destroy(Scenario $scenario, Scene $scene, NpcLine $line): RedirectResponse
    {
        $this->ensureLine($scenario, $scene, $line);
        $line->delete();

        return back()->with('success', 'NPC line deleted.');
    }

    private function validated(Request $request, Scene $scene): array
    {
        return $request->validate([
            'lt' => ['required', 'string', 'max:1000'],
            'en' => ['required', 'string', 'max:1000'],
            'cefr_level' => ['nullable', Rule::enum(CefrLevel::class)],
            'trigger_goal_id' => [
                'nullable',
                Rule::exists('goals', 'id')->where('scene_id', $scene->id),
            ],
            'priority' => ['required', 'integer', 'min:0', 'max:1000'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ]);
    }

    private function ensureScene(Scenario $scenario, Scene $scene): void
    {
        abort_unless($scene->scenario_id === $scenario->id, 404);
    }

    private function ensureLine(Scenario $scenario, Scene $scene, NpcLine $line): void
    {
        $this->ensureScene($scenario, $scene);
        abort_unless($line->scene_id === $scene->id, 404);
    }
}
