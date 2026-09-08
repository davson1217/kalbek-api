<?php

namespace App\Http\Controllers\Cms;

use App\CefrLevel;
use App\ContentStatus;
use App\Http\Controllers\Controller;
use App\Models\Scenario;
use App\Models\ScenarioNote;
use App\Services\Content\SyncContentTranslations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ScenarioNoteController extends Controller
{
    public function store(Request $request, Scenario $scenario, SyncContentTranslations $translations): RedirectResponse
    {
        abort_if($scenario->note()->exists(), 409, 'This scenario already has a preparation note.');

        $data = $this->validated($request, $translations);
        $note = $scenario->note()->create($this->contentData($data));
        $translations->sync($note, $data['translations'] ?? [], ['title', 'body']);

        return back()->with('success', 'Preparation note created.');
    }

    public function update(Request $request, Scenario $scenario, ScenarioNote $note, SyncContentTranslations $translations): RedirectResponse
    {
        $this->ensureNote($scenario, $note);

        $data = $this->validated($request, $translations);
        $note->update($this->contentData($data));
        $translations->sync($note, $data['translations'] ?? [], ['title', 'body']);

        return back()->with('success', 'Preparation note updated.');
    }

    public function destroy(Scenario $scenario, ScenarioNote $note): RedirectResponse
    {
        $this->ensureNote($scenario, $note);
        $note->delete();

        return back()->with('success', 'Preparation note deleted.');
    }

    private function validated(Request $request, SyncContentTranslations $translations): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:5000'],
            'cefr_level' => ['nullable', Rule::enum(CefrLevel::class)],
            'estimated_minutes' => ['required', 'integer', 'min:1', 'max:30'],
            'status' => ['required', Rule::enum(ContentStatus::class)],
            ...$translations->rules(['title', 'body']),
        ]);
    }

    private function contentData(array $data): array
    {
        unset($data['translations']);

        return $data;
    }

    private function ensureNote(Scenario $scenario, ScenarioNote $note): void
    {
        abort_unless($note->scenario_id === $scenario->id, 404);
    }
}
