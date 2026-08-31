<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\Scenario;
use App\Models\Scene;
use App\Models\SceneProp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ScenePropController extends Controller
{
    public function store(Request $request, Scenario $scenario, Scene $scene): RedirectResponse
    {
        $this->ensureScene($scenario, $scene);
        $scene->props()->create($this->validated($request));

        return back()->with('success', 'Prop created.');
    }

    public function update(Request $request, Scenario $scenario, Scene $scene, SceneProp $prop): RedirectResponse
    {
        $this->ensureProp($scenario, $scene, $prop);
        $prop->update($this->validated($request));

        return back()->with('success', 'Prop updated.');
    }

    public function destroy(Scenario $scenario, Scene $scene, SceneProp $prop): RedirectResponse
    {
        $this->ensureProp($scenario, $scene, $prop);
        $prop->delete();

        return back()->with('success', 'Prop deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'type' => ['required', Rule::in(['menu_item'])],
            'lt' => ['required', 'string', 'max:500'],
            'en' => ['required', 'string', 'max:500'],
            'price' => ['nullable', 'string', 'max:80'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ]);
    }

    private function ensureScene(Scenario $scenario, Scene $scene): void
    {
        abort_unless($scene->scenario_id === $scenario->id, 404);
    }

    private function ensureProp(Scenario $scenario, Scene $scene, SceneProp $prop): void
    {
        $this->ensureScene($scenario, $scene);
        abort_unless($prop->scene_id === $scene->id, 404);
    }
}
