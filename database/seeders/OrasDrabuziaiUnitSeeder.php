<?php

namespace Database\Seeders;

use App\ContentStatus;
use App\Models\Character;
use App\Models\Goal;
use App\Models\Language;
use App\Models\Scenario;
use App\Models\Scene;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OrasDrabuziaiUnitSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $language = Language::query()->where('code', 'lt')->firstOrFail();
            $character = Character::query()->where('slug', 'gabija')->firstOrFail();
            $unit = Unit::query()->updateOrCreate(
                ['slug' => 'oras-ir-drabuziai'],
                [
                    'language_id' => $language->id,
                    'title' => 'Oras ir drabužiai',
                    'description' => 'A practical unit for weather, temperature, simple clothing words, colors, sizes, and basic needs.',
                    'cefr_level' => 'a1',
                    'status' => ContentStatus::Published,
                    'sort_order' => 100,
                    'published_at' => now(),
                ],
            );

            $this->syncTranslations($unit, [
                'title' => ['en' => 'Weather and Clothes', 'lt' => 'Oras ir drabužiai'],
                'description' => [
                    'en' => 'A practical unit for weather, temperature, simple clothing words, colors, sizes, and basic needs.',
                    'lt' => 'Praktiškas skyrius apie orą, temperatūrą, paprastus drabužių žodžius, spalvas, dydžius ir pagrindinius poreikius.',
                ],
            ]);

            $scenarios = $this->scenarios();
            $this->deleteRemovedScenarios($unit, $scenarios);

            foreach ($scenarios as $scenarioData) {
                $scenario = Scenario::query()->updateOrCreate(
                    ['slug' => $scenarioData['slug']],
                    [
                        'language_id' => $language->id,
                        'unit_id' => $unit->id,
                        'character_id' => $character->id,
                        'title' => $scenarioData['title'],
                        'subtitle' => $scenarioData['subtitle'],
                        'description' => $scenarioData['description'],
                        'emoji' => $scenarioData['emoji'],
                        'tone' => $scenarioData['tone'],
                        'cefr_level' => 'a1',
                        'start_scene_slug' => $scenarioData['start_scene'],
                        'status' => ContentStatus::Published,
                        'sort_order' => $scenarioData['sort_order'],
                        'published_at' => now(),
                    ],
                );

                $this->syncTranslations($scenario, $scenarioData['translations']);
                $this->upsertNote($scenario, $scenarioData['note']);
                $this->deleteRemovedScenes($scenario, $scenarioData['scenes']);
                $scenes = $this->upsertScenes($scenario, $scenarioData['scenes']);
                $this->replaceSceneContent($scenes, $scenarioData['scenes']);
            }
        });
    }

    private function upsertNote(Scenario $scenario, array $note): void
    {
        $model = $scenario->note()->updateOrCreate([], [
            'title' => $note['title'],
            'body' => $note['body'],
            'cefr_level' => 'a1',
            'estimated_minutes' => 2,
            'status' => ContentStatus::Published,
        ]);

        $this->syncTranslations($model, $note['translations']);
    }

    /**
     * @param  array<int, array<string, mixed>>  $sceneData
     * @return Collection<string, Scene>
     */
    private function upsertScenes(Scenario $scenario, array $sceneData): Collection
    {
        foreach ($sceneData as $index => $scene) {
            $model = $scenario->scenes()->updateOrCreate(
                ['slug' => $scene['slug']],
                [
                    'title' => $scene['title'],
                    'setting' => $scene['setting'],
                    'cefr_level' => 'a1',
                    'sort_order' => ($index + 1) * 10,
                ],
            );

            $this->syncTranslations($model, $scene['translations']);
        }

        return $scenario->scenes()->get()->keyBy('slug');
    }

    /**
     * @param  Collection<string, Scene>  $scenes
     * @param  array<int, array<string, mixed>>  $sceneData
     */
    private function replaceSceneContent(Collection $scenes, array $sceneData): void
    {
        foreach ($sceneData as $scene) {
            $sceneModel = $scenes->get($scene['slug']);

            foreach ($scene['goals'] as $index => $goal) {
                $goalModel = $sceneModel->goals()->updateOrCreate(
                    ['slug' => $goal['slug']],
                    [
                        'next_scene_id' => $goal['next'] ? $scenes->get($goal['next'])?->id : null,
                        'label' => $goal['label'],
                        'intent' => $goal['intent'],
                        'example' => $goal['example'],
                        'accepted_phrases' => $goal['accepted_phrases'] ?? null,
                        'cefr_level' => 'a1',
                        'sort_order' => ($index + 1) * 10,
                    ],
                );

                $this->syncTranslations($goalModel, $goal['translations']);
            }

            $sceneModel->goals()->whereNotIn('slug', collect($scene['goals'])->pluck('slug')->all())->delete();
        }

        $goals = Goal::query()->whereIn('scene_id', $scenes->pluck('id'))->get()->keyBy('slug');

        foreach ($sceneData as $scene) {
            $sceneModel = $scenes->get($scene['slug']);
            $sceneModel->npcLines()->delete();
            $sceneModel->props()->delete();

            foreach ($scene['lines'] as $index => $line) {
                $npcLine = $sceneModel->npcLines()->create([
                    'trigger_goal_id' => ($line['trigger_goal'] ?? null) ? $goals->get($line['trigger_goal'])?->id : null,
                    'target_text' => $line['target_text'],
                    'support_translation' => $line['support_translation'],
                    'cefr_level' => 'a1',
                    'priority' => $line['priority'] ?? 0,
                    'sort_order' => ($index + 1) * 10,
                ]);

                $this->syncTranslations($npcLine, [
                    'support_translation' => ['en' => $line['support_translation'], 'lt' => $line['target_text']],
                ]);
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $sceneData
     */
    private function deleteRemovedScenes(Scenario $scenario, array $sceneData): void
    {
        $scenario->scenes()
            ->whereNotIn('slug', collect($sceneData)->pluck('slug')->all())
            ->get()
            ->each
            ->delete();
    }

    /**
     * @param  array<int, array<string, mixed>>  $scenarioData
     */
    private function deleteRemovedScenarios(Unit $unit, array $scenarioData): void
    {
        $unit->scenarios()
            ->whereNotIn('slug', collect($scenarioData)->pluck('slug')->all())
            ->get()
            ->each
            ->delete();
    }

    private function syncTranslations($model, array $translations): void
    {
        foreach ($translations as $field => $locales) {
            foreach ($locales as $locale => $value) {
                $model->translations()->updateOrCreate(
                    ['field' => $field, 'locale' => $locale],
                    ['value' => $value],
                );
            }
        }
    }

    private function scenarios(): array
    {
        return [
            $this->scenario([
                'slug' => 'koks-oras',
                'title' => 'Koks oras?',
                'title_en' => 'What Is the Weather Like?',
                'subtitle' => 'Say simple weather',
                'subtitle_lt' => 'Pasakykite paprastą orą',
                'description' => 'Say whether the weather is good, bad, sunny, or rainy.',
                'description_lt' => 'Pasakykite, ar oras geras, blogas, saulėtas arba lietingas.',
                'emoji' => '☀️',
                'tone' => 'primary',
                'sort_order' => 101,
                'scene_slug' => 'oras',
                'goal_slug' => 'say-weather',
                'goal_label' => 'Say the weather',
                'goal_label_lt' => 'Pasakykite, koks oras',
                'goal_intent' => 'The learner describes the weather with one simple phrase.',
                'goal_intent_lt' => 'Mokinys viena paprasta fraze apibūdina orą.',
                'example' => 'Šiandien oras geras.',
                'accepted_phrases' => [
                    'Šiandien oras geras.',
                    'Šiandien oras blogas.',
                    'Šiandien saulėta.',
                    'Šiandien lyja.',
                    'Oras geras.',
                    'Oras blogas.',
                ],
                'openings' => ['Koks šiandien oras?', 'Pasakykite, koks oras.'],
                'replies' => ['Gerai. Jūs pasakėte apie orą.', 'Puiku. Oras aiškus.'],
                'note' => [
                    'en' => [
                        'This lesson starts weather with very short phrases.',
                        'Useful phrases:',
                        '- Koks oras? = What is the weather like?',
                        '- Oras geras. = The weather is good.',
                        '- Oras blogas. = The weather is bad.',
                        '- Šiandien saulėta. = Today it is sunny.',
                        '- Šiandien lyja. = Today it is raining.',
                        'Tiny grammar: šiandien means today.',
                    ],
                    'lt' => [
                        'Ši pamoka pradeda oro temą labai trumpomis frazėmis.',
                        'Naudingos frazės:',
                        '- Koks oras?',
                        '- Oras geras.',
                        '- Oras blogas.',
                        '- Šiandien saulėta.',
                        '- Šiandien lyja.',
                        'Maža gramatikos pastaba: šiandien reiškia today.',
                    ],
                ],
            ]),
            $this->scenario([
                'slug' => 'silta-ar-salta',
                'title' => 'Šilta ar šalta?',
                'title_en' => 'Warm or Cold?',
                'subtitle' => 'Say how it feels outside',
                'subtitle_lt' => 'Pasakykite, kaip jaučiasi lauke',
                'description' => 'Say that it is warm, cold, or very cold.',
                'description_lt' => 'Pasakykite, kad šilta, šalta arba labai šalta.',
                'emoji' => '🌡️',
                'tone' => 'mint',
                'sort_order' => 102,
                'scene_slug' => 'temperatura',
                'goal_slug' => 'say-temperature',
                'goal_label' => 'Say the temperature feeling',
                'goal_label_lt' => 'Pasakykite temperatūros pojūtį',
                'goal_intent' => 'The learner says whether it is warm or cold.',
                'goal_intent_lt' => 'Mokinys pasako, ar šilta, ar šalta.',
                'example' => 'Lauke šalta.',
                'openings' => ['Ar lauke šilta ar šalta?', 'Kaip lauke: šilta ar šalta?'],
                'replies' => ['Gerai. Supratau, kaip yra lauke.', 'Puiku. Aiškiai pasakėte.'],
                'note' => [
                    'en' => [
                        'This lesson practises temperature words.',
                        'Useful phrases:',
                        '- Lauke šilta. = It is warm outside.',
                        '- Lauke šalta. = It is cold outside.',
                        '- Labai šalta. = Very cold.',
                        '- Man šalta. = I am cold.',
                        '- Man šilta. = I am warm.',
                        'Focus: lauke means outside.',
                    ],
                    'lt' => [
                        'Ši pamoka praktikuoja temperatūros žodžius.',
                        'Naudingos frazės:',
                        '- Lauke šilta.',
                        '- Lauke šalta.',
                        '- Labai šalta.',
                        '- Man šalta.',
                        '- Man šilta.',
                        'Svarbu: lauke reiškia outside.',
                    ],
                ],
            ]),
            $this->scenario([
                'slug' => 'ka-apsirengti',
                'title' => 'Ką apsirengti?',
                'title_en' => 'What to Wear?',
                'subtitle' => 'Say one clothing item',
                'subtitle_lt' => 'Pasakykite vieną drabužį',
                'description' => 'Say what you need to wear in simple weather.',
                'description_lt' => 'Pasakykite, ką reikia apsirengti paprastu oru.',
                'emoji' => '🧥',
                'tone' => 'sky',
                'sort_order' => 103,
                'scene_slug' => 'drabuzis',
                'goal_slug' => 'say-clothing',
                'goal_label' => 'Say what to wear',
                'goal_label_lt' => 'Pasakykite, ką apsirengti',
                'goal_intent' => 'The learner names one clothing item that fits the weather.',
                'goal_intent_lt' => 'Mokinys pasako vieną drabužį, kuris tinka orui.',
                'example' => 'Reikia striukės.',
                'openings' => ['Ką apsirengsite?', 'Ko reikia šiandien?'],
                'replies' => ['Gerai. Tai tinka.', 'Puiku. Pasirinkimas aiškus.'],
                'note' => [
                    'en' => [
                        'This lesson connects weather and clothes.',
                        'Useful phrases:',
                        '- Reikia striukės. = A jacket is needed.',
                        '- Reikia megztinio. = A sweater is needed.',
                        '- Reikia kepurės. = A hat is needed.',
                        '- Nereikia striukės. = A jacket is not needed.',
                        'Tiny grammar: reikia means need or is needed.',
                    ],
                    'lt' => [
                        'Ši pamoka sujungia orą ir drabužius.',
                        'Naudingos frazės:',
                        '- Reikia striukės.',
                        '- Reikia megztinio.',
                        '- Reikia kepurės.',
                        '- Nereikia striukės.',
                        'Maža gramatikos pastaba: reikia reiškia need arba is needed.',
                    ],
                ],
            ]),
            $this->scenario([
                'slug' => 'man-reikia-drabuzio',
                'title' => 'Man reikia drabužio',
                'title_en' => 'I Need Clothing',
                'subtitle' => 'Ask for clothing in a shop',
                'subtitle_lt' => 'Paprašykite drabužio parduotuvėje',
                'description' => 'Say that you need a jacket, sweater, hat, or shoes.',
                'description_lt' => 'Pasakykite, kad jums reikia striukės, megztinio, kepurės arba batų.',
                'emoji' => '👕',
                'tone' => 'amber',
                'sort_order' => 104,
                'scene_slug' => 'poreikis',
                'goal_slug' => 'ask-clothing-item',
                'goal_label' => 'Ask for clothing',
                'goal_label_lt' => 'Paprašykite drabužio',
                'goal_intent' => 'The learner asks for a clothing item in a simple shop context.',
                'goal_intent_lt' => 'Mokinys paprašo drabužio paprastame parduotuvės kontekste.',
                'example' => 'Man reikia megztinio.',
                'openings' => ['Ko jums reikia?', 'Kokio drabužio ieškote?'],
                'replies' => ['Žinoma. Galiu padėti.', 'Gerai. Pažiūrėkime.'],
                'note' => [
                    'en' => [
                        'This lesson practises asking for clothing.',
                        'Useful phrases:',
                        '- Man reikia striukės. = I need a jacket.',
                        '- Man reikia megztinio. = I need a sweater.',
                        '- Man reikia kepurės. = I need a hat.',
                        '- Man reikia batų. = I need shoes.',
                        'Focus: man reikia means I need.',
                    ],
                    'lt' => [
                        'Ši pamoka praktikuoja drabužio prašymą.',
                        'Naudingos frazės:',
                        '- Man reikia striukės.',
                        '- Man reikia megztinio.',
                        '- Man reikia kepurės.',
                        '- Man reikia batų.',
                        'Svarbu: man reikia reiškia I need.',
                    ],
                ],
            ]),
            $this->scenario([
                'slug' => 'spalva-ir-dydis',
                'title' => 'Spalva ir dydis',
                'title_en' => 'Color and Size',
                'subtitle' => 'Say a color or size',
                'subtitle_lt' => 'Pasakykite spalvą arba dydį',
                'description' => 'Say a simple color or clothing size.',
                'description_lt' => 'Pasakykite paprastą spalvą arba drabužio dydį.',
                'emoji' => '🎨',
                'tone' => 'berry',
                'sort_order' => 105,
                'scene_slug' => 'pasirinkimas',
                'goal_slug' => 'say-color-size',
                'goal_label' => 'Say color or size',
                'goal_label_lt' => 'Pasakykite spalvą arba dydį',
                'goal_intent' => 'The learner says a simple clothing color or size.',
                'goal_intent_lt' => 'Mokinys pasako paprastą drabužio spalvą arba dydį.',
                'example' => 'Noriu mėlynos striukės.',
                'openings' => ['Kokios spalvos norite?', 'Kokio dydžio reikia?'],
                'replies' => ['Gerai. Pažiūrėkime.', 'Puiku. Pasirinkimas aiškus.'],
                'note' => [
                    'en' => [
                        'This lesson adds color and size.',
                        'Useful phrases:',
                        '- Noriu mėlynos striukės. = I want a blue jacket.',
                        '- Noriu juodos kepurės. = I want a black hat.',
                        '- Reikia mažo dydžio. = A small size is needed.',
                        '- Reikia didelio dydžio. = A large size is needed.',
                        'A1 goal: one color or one size is enough.',
                    ],
                    'lt' => [
                        'Ši pamoka prideda spalvą ir dydį.',
                        'Naudingos frazės:',
                        '- Noriu mėlynos striukės.',
                        '- Noriu juodos kepurės.',
                        '- Reikia mažo dydžio.',
                        '- Reikia didelio dydžio.',
                        'A1 tikslas: užtenka vienos spalvos arba vieno dydžio.',
                    ],
                ],
            ]),
            [
                'slug' => 'oras-ir-apranga',
                'title' => 'Oras ir apranga',
                'subtitle' => 'Put weather and clothing together',
                'description' => 'Say the weather, ask for a clothing item, choose a color or size, and finish politely.',
                'emoji' => '🧣',
                'tone' => 'primary',
                'sort_order' => 106,
                'start_scene' => 'oras',
                'translations' => [
                    'title' => ['en' => 'Weather and Outfit', 'lt' => 'Oras ir apranga'],
                    'subtitle' => ['en' => 'Put weather and clothing together', 'lt' => 'Sujunkite orą ir aprangą'],
                    'description' => ['en' => 'Say the weather, ask for a clothing item, choose a color or size, and finish politely.', 'lt' => 'Pasakykite orą, paprašykite drabužio, pasirinkite spalvą arba dydį ir mandagiai užbaikite.'],
                ],
                'note' => [
                    'title' => 'Before: Weather and Outfit',
                    'body' => implode("\n\n", [
                        'This capstone combines weather and clothing phrases from the unit.',
                        'Useful flow:',
                        '- Šiandien šalta. = Today it is cold.',
                        '- Man reikia striukės. = I need a jacket.',
                        '- Noriu mėlynos striukės. = I want a blue jacket.',
                        '- Ačiū. = Thank you.',
                        'Goal: explain a simple clothing need based on the weather.',
                    ]),
                    'translations' => [
                        'title' => ['en' => 'Before: Weather and Outfit', 'lt' => 'Prieš scenarijų: Oras ir apranga'],
                        'body' => ['en' => implode("\n\n", [
                            'This capstone combines weather and clothing phrases from the unit.',
                            'Useful flow:',
                            '- Šiandien šalta. = Today it is cold.',
                            '- Man reikia striukės. = I need a jacket.',
                            '- Noriu mėlynos striukės. = I want a blue jacket.',
                            '- Ačiū. = Thank you.',
                            'Goal: explain a simple clothing need based on the weather.',
                        ]), 'lt' => implode("\n\n", [
                            'Šis pakartojimo scenarijus sujungia oro ir drabužių frazes iš šio skyriaus.',
                            'Naudinga seka:',
                            '- Šiandien šalta.',
                            '- Man reikia striukės.',
                            '- Noriu mėlynos striukės.',
                            '- Ačiū.',
                            'Tikslas: paprastai paaiškinkite drabužio poreikį pagal orą.',
                        ])],
                    ],
                ],
                'scenes' => [
                    $this->capstoneScene('oras', 'Weather', 'Oras', 'Gabija asks about today’s weather.', 'Gabija klausia apie šiandienos orą.', ['Koks šiandien oras?', 'Kaip šiandien lauke?'], ['Gerai. Oras aiškus.', 'Puiku. Supratau.'], 'capstone-weather', 'Say the weather', 'Pasakykite, koks oras', 'The learner says what the weather is like.', 'Mokinys pasako, koks yra oras.', 'Šiandien šalta.', 'drabuzis'),
                    $this->capstoneScene('drabuzis', 'Clothing need', 'Drabužio poreikis', 'Gabija asks what clothing the learner needs.', 'Gabija klausia, kokio drabužio mokiniui reikia.', ['Ko jums reikia?', 'Kokio drabužio reikia?'], ['Žinoma. Galiu padėti.', 'Gerai. Pažiūrėkime.'], 'capstone-clothing-need', 'Ask for clothing', 'Paprašykite drabužio', 'The learner says what clothing item they need.', 'Mokinys pasako, kokio drabužio reikia.', 'Man reikia striukės.', 'pasirinkimas'),
                    $this->capstoneScene('pasirinkimas', 'Choice', 'Pasirinkimas', 'Gabija asks for one color or size choice.', 'Gabija klausia vienos spalvos arba dydžio.', ['Kokios spalvos norite?', 'Kokio dydžio reikia?'], ['Gerai. Pasirinkimas aiškus.', 'Puiku. Ačiū.'], 'capstone-color-size', 'Choose color or size', 'Pasirinkite spalvą arba dydį', 'The learner chooses a color or size and may finish politely.', 'Mokinys pasirenka spalvą arba dydį ir gali mandagiai užbaigti.', 'Noriu mėlynos striukės.', null),
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function scenario(array $data): array
    {
        return [
            'slug' => $data['slug'],
            'title' => $data['title'],
            'subtitle' => $data['subtitle'],
            'description' => $data['description'],
            'emoji' => $data['emoji'],
            'tone' => $data['tone'],
            'sort_order' => $data['sort_order'],
            'start_scene' => $data['scene_slug'],
            'translations' => [
                'title' => ['en' => $data['title_en'], 'lt' => $data['title']],
                'subtitle' => ['en' => $data['subtitle'], 'lt' => $data['subtitle_lt']],
                'description' => ['en' => $data['description'], 'lt' => $data['description_lt']],
            ],
            'note' => [
                'title' => 'Before: '.$data['title_en'],
                'body' => implode("\n\n", $data['note']['en']),
                'translations' => [
                    'title' => ['en' => 'Before: '.$data['title_en'], 'lt' => 'Prieš scenarijų: '.$data['title']],
                    'body' => ['en' => implode("\n\n", $data['note']['en']), 'lt' => implode("\n\n", $data['note']['lt'])],
                ],
            ],
            'scenes' => [[
                'slug' => $data['scene_slug'],
                'title' => $data['title'],
                'setting' => $data['description'],
                'translations' => [
                    'title' => ['en' => $data['title_en'], 'lt' => $data['title']],
                    'setting' => ['en' => $data['description'], 'lt' => $data['description_lt']],
                ],
                'lines' => $this->lines($data['openings'], $data['replies'], $data['goal_slug']),
                'goals' => [[
                    'slug' => $data['goal_slug'],
                    'label' => $data['goal_label'],
                    'intent' => $data['goal_intent'],
                    'example' => $data['example'],
                    'next' => null,
                    'accepted_phrases' => $data['accepted_phrases'] ?? null,
                    'translations' => [
                        'label' => ['en' => $data['goal_label'], 'lt' => $data['goal_label_lt']],
                        'intent' => ['en' => $data['goal_intent'], 'lt' => $data['goal_intent_lt']],
                    ],
                ]],
            ]],
        ];
    }

    /**
     * @param  array<int, string>  $openings
     * @param  array<int, string>  $replies
     * @return array<string, mixed>
     */
    private function capstoneScene(string $slug, string $title, string $titleLt, string $setting, string $settingLt, array $openings, array $replies, string $goalSlug, string $goalLabel, string $goalLabelLt, string $intent, string $intentLt, string $example, ?string $next): array
    {
        return [
            'slug' => $slug,
            'title' => $title,
            'setting' => $setting,
            'translations' => [
                'title' => ['en' => $title, 'lt' => $titleLt],
                'setting' => ['en' => $setting, 'lt' => $settingLt],
            ],
            'lines' => $this->lines($openings, $replies, $goalSlug),
            'goals' => [[
                'slug' => $goalSlug,
                'label' => $goalLabel,
                'intent' => $intent,
                'example' => $example,
                'next' => $next,
                'translations' => [
                    'label' => ['en' => $goalLabel, 'lt' => $goalLabelLt],
                    'intent' => ['en' => $intent, 'lt' => $intentLt],
                ],
            ]],
        ];
    }

    /**
     * @param  array<int, string>  $openings
     * @param  array<int, string>  $replies
     * @return array<int, array<string, mixed>>
     */
    private function lines(array $openings, array $replies, string $goalSlug): array
    {
        return collect($openings)
            ->map(fn (string $line): array => ['target_text' => $line, 'support_translation' => $this->translateLine($line)])
            ->merge(collect($replies)->map(fn (string $line): array => [
                'target_text' => $line,
                'support_translation' => $this->translateLine($line),
                'trigger_goal' => $goalSlug,
                'priority' => 100,
            ]))
            ->all();
    }

    private function translateLine(string $line): string
    {
        return match ($line) {
            'Koks šiandien oras?' => 'What is the weather like today?',
            'Pasakykite, koks oras.' => 'Say what the weather is like.',
            'Gerai. Jūs pasakėte apie orą.' => 'Good. You spoke about the weather.',
            'Puiku. Oras aiškus.' => 'Great. The weather is clear.',
            'Ar lauke šilta ar šalta?' => 'Is it warm or cold outside?',
            'Kaip lauke: šilta ar šalta?' => 'What is it like outside: warm or cold?',
            'Gerai. Supratau, kaip yra lauke.' => 'Good. I understood what it is like outside.',
            'Puiku. Aiškiai pasakėte.' => 'Great. You said it clearly.',
            'Ką apsirengsite?' => 'What will you wear?',
            'Ko reikia šiandien?' => 'What is needed today?',
            'Gerai. Tai tinka.' => 'Good. That works.',
            'Ko jums reikia?' => 'What do you need?',
            'Kokio drabužio ieškote?' => 'What clothing item are you looking for?',
            'Žinoma. Galiu padėti.' => 'Of course. I can help.',
            'Gerai. Pažiūrėkime.' => 'Good. Let’s look.',
            'Kokios spalvos norite?' => 'What color do you want?',
            'Kokio dydžio reikia?' => 'What size is needed?',
            'Kaip šiandien lauke?' => 'What is it like outside today?',
            'Gerai. Oras aiškus.' => 'Good. The weather is clear.',
            'Puiku. Supratau.' => 'Great. I understand.',
            'Gerai. Pasirinkimas aiškus.' => 'Good. The choice is clear.',
            'Puiku. Pasirinkimas aiškus.' => 'Great. The choice is clear.',
            'Puiku. Ačiū.' => 'Great. Thank you.',
            default => $line,
        };
    }
}
