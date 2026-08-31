<?php

namespace App\Http\Controllers\Cms;

use App\CefrLevel;
use App\Http\Controllers\Controller;
use App\Models\Scenario;
use App\Models\Scene;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SceneController extends Controller
{
    public function store(Request $request, Scenario $scenario): RedirectResponse
    {
        $scenario->scenes()->create($this->validated($request, $scenario));

        return back()->with('success', 'Scene created.');
    }

    public function update(Request $request, Scenario $scenario, Scene $scene): RedirectResponse
    {
        $this->ensureScene($scenario, $scene);
        $scene->update($this->validated($request, $scenario, $scene));

        return back()->with('success', 'Scene updated.');
    }

    public function destroy(Scenario $scenario, Scene $scene): RedirectResponse
    {
        $this->ensureScene($scenario, $scene);
        $scene->delete();

        return back()->with('success', 'Scene deleted.');
    }

    private function validated(Request $request, Scenario $scenario, ?Scene $scene = null): array
    {
        return $request->validate([
            'slug' => [
                'required',
                'string',
                'max:100',
                Rule::unique('scenes', 'slug')->where('scenario_id', $scenario->id)->ignore($scene),
            ],
            'setting' => ['required', 'string', 'max:1000'],
            'cefr_level' => ['nullable', Rule::enum(CefrLevel::class)],
            'sort_order' => ['required', 'integer', 'min:0'],
        ]);
    }

    private function ensureScene(Scenario $scenario, Scene $scene): void
    {
        abort_unless($scene->scenario_id === $scenario->id, 404);
    }
}
