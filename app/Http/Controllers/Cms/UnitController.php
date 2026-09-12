<?php

namespace App\Http\Controllers\Cms;

use App\CefrLevel;
use App\ContentStatus;
use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Models\Unit;
use App\Services\Content\SyncContentTranslations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UnitController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Units/Index', [
            'units' => Unit::query()
                ->with(['language', 'translations'])
                ->withCount('scenarios')
                ->orderBy('sort_order')
                ->orderBy('title')
                ->get()
                ->map(fn (Unit $unit): array => $this->summary($unit)),
            'languages' => $this->languageOptions(),
            'statuses' => array_column(ContentStatus::cases(), 'value'),
            'levels' => array_column(CefrLevel::cases(), 'value'),
        ]);
    }

    public function store(Request $request, SyncContentTranslations $translations): RedirectResponse
    {
        $data = $this->validated($request);
        $unit = Unit::query()->create($this->contentData($data));
        $translations->sync($unit, $data['translations'] ?? [], ['title', 'description']);

        return back()->with('success', 'Unit created.');
    }

    public function update(Request $request, Unit $unit, SyncContentTranslations $translations): RedirectResponse
    {
        $data = $this->validated($request, $unit);
        $unit->update($this->contentData($data));
        $translations->sync($unit, $data['translations'] ?? [], ['title', 'description']);

        return back()->with('success', 'Unit updated.');
    }

    private function validated(Request $request, ?Unit $unit = null): array
    {
        return $request->validate([
            'language_id' => ['required', 'integer', 'exists:languages,id'],
            'slug' => ['required', 'string', 'max:100', Rule::unique('units', 'slug')->ignore($unit)],
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:1000'],
            'cefr_level' => ['nullable', Rule::enum(CefrLevel::class)],
            'status' => ['required', Rule::enum(ContentStatus::class)],
            'sort_order' => ['required', 'integer', 'min:0'],
            'translations' => ['nullable', 'array'],
            'translations.*' => ['array'],
            'translations.*.*' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    private function contentData(array $data): array
    {
        unset($data['translations']);

        return [
            ...$data,
            'published_at' => $data['status'] === ContentStatus::Published->value ? now() : null,
        ];
    }

    private function summary(Unit $unit): array
    {
        return [
            'id' => $unit->id,
            'language_id' => $unit->language_id,
            'slug' => $unit->slug,
            'title' => $unit->title,
            'description' => $unit->description,
            'cefr_level' => $unit->cefr_level?->value,
            'status' => $unit->status->value,
            'sort_order' => $unit->sort_order,
            'published_at' => $unit->published_at?->toDateTimeString(),
            'scenarios_count' => $unit->scenarios_count ?? 0,
            'language' => $unit->language ? [
                'id' => $unit->language->id,
                'code' => $unit->language->code,
                'name' => $unit->language->name,
                'native_name' => $unit->language->native_name,
                'support_language_code' => $unit->language->support_language_code,
                'support_language_name' => $unit->language->support_language_name,
            ] : null,
            'translations' => $unit->translationMap(),
        ];
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
