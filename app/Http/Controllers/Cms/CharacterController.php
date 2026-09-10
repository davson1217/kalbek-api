<?php

namespace App\Http\Controllers\Cms;

use App\ContentStatus;
use App\Http\Controllers\Controller;
use App\Models\Character;
use App\Models\Language;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CharacterController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Characters/Index', [
            'characters' => Character::query()
                ->with('language')
                ->withCount('scenarios')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn (Character $character): array => [
                    'id' => $character->id,
                    'language_id' => $character->language_id,
                    'language' => $character->language ? [
                        'id' => $character->language->id,
                        'code' => $character->language->code,
                        'name' => $character->language->name,
                        'native_name' => $character->language->native_name,
                    ] : null,
                    'slug' => $character->slug,
                    'name' => $character->name,
                    'role' => $character->role,
                    'image_path' => $character->image_path,
                    'intro' => $character->intro,
                    'tts_voice' => $character->tts_voice,
                    'speaking_style' => $character->speaking_style,
                    'praise_lines' => $character->praise_lines,
                    'encouragement_lines' => $character->encouragement_lines,
                    'sort_order' => $character->sort_order,
                    'status' => $character->status->value,
                    'scenarios_count' => $character->scenarios_count,
                ]),
            'statuses' => array_column(ContentStatus::cases(), 'value'),
            'languages' => $this->languageOptions(),
            'ttsVoiceOptions' => $this->ttsVoiceOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Character::query()->create($this->validated($request));

        return back()->with('success', 'Character created.');
    }

    public function update(Request $request, Character $character): RedirectResponse
    {
        $character->update($this->validated($request, $character));

        return back()->with('success', 'Character updated.');
    }

    private function validated(Request $request, ?Character $character = null): array
    {
        $data = $request->validate([
            'language_id' => ['required', 'integer', 'exists:languages,id'],
            'slug' => ['required', 'string', 'max:80', Rule::unique('characters', 'slug')->ignore($character)],
            'name' => ['required', 'string', 'max:120'],
            'role' => ['required', 'string', 'max:120'],
            'image_path' => ['nullable', 'string', 'max:255'],
            'intro' => ['nullable', 'string', 'max:500'],
            'tts_voice' => ['nullable', 'string', 'max:120'],
            'speaking_style' => ['nullable', 'string', 'max:1000'],
            'praise_lines' => ['nullable', 'string'],
            'encouragement_lines' => ['nullable', 'string'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'status' => ['required', Rule::enum(ContentStatus::class)],
        ]);

        $data['praise_lines'] = $this->lines($data['praise_lines'] ?? '');
        $data['encouragement_lines'] = $this->lines($data['encouragement_lines'] ?? '');

        return $data;
    }

    private function lines(string $value): array
    {
        return collect(preg_split('/\r\n|\r|\n/', $value) ?: [])
            ->map(fn (string $line): string => trim($line))
            ->filter()
            ->values()
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

    private function ttsVoiceOptions(): array
    {
        $groups = config('services.kalbek.tts_voice_options', []);
        $model = strtolower((string) config('services.kalbek.tts_model', ''));
        $provider = strtolower((string) config('ai.default_for_audio', config('ai.default', '')));
        $group = str_contains($model, 'gemini') || str_contains($provider, 'gemini') || str_contains($provider, 'openrouter')
            ? 'gemini'
            : 'openai';

        return collect($groups[$group] ?? [])
            ->map(fn (string $label, string $value): array => [
                'value' => $value,
                'label' => $label,
            ])
            ->values()
            ->all();
    }
}
