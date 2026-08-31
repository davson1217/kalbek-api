<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\Language;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LanguageController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Languages/Index', [
            'languages' => Language::query()
                ->withCount(['characters', 'scenarios'])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn (Language $language): array => [
                    'id' => $language->id,
                    'code' => $language->code,
                    'name' => $language->name,
                    'native_name' => $language->native_name,
                    'support_language_code' => $language->support_language_code,
                    'support_language_name' => $language->support_language_name,
                    'status' => $language->status,
                    'default_voice' => $language->default_voice,
                    'sort_order' => $language->sort_order,
                    'characters_count' => $language->characters_count,
                    'scenarios_count' => $language->scenarios_count,
                    'updated_at' => $language->updated_at?->toDateTimeString(),
                ]),
            'statuses' => ['active', 'draft', 'archived'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Language::query()->create($this->validated($request));

        return back()->with('success', 'Language created.');
    }

    public function update(Request $request, Language $language): RedirectResponse
    {
        $language->update($this->validated($request, $language));

        return back()->with('success', 'Language updated.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Language $language = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'lowercase', 'max:10', Rule::unique('languages', 'code')->ignore($language)],
            'name' => ['required', 'string', 'max:120'],
            'native_name' => ['required', 'string', 'max:120'],
            'support_language_code' => ['required', 'string', 'lowercase', 'max:10'],
            'support_language_name' => ['required', 'string', 'max:120'],
            'status' => ['required', Rule::in(['active', 'draft', 'archived'])],
            'default_voice' => ['nullable', 'string', 'max:120'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ]);
    }
}
