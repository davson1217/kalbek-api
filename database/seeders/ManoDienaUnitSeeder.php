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

class ManoDienaUnitSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $language = Language::query()->where('code', 'lt')->firstOrFail();
            $character = Character::query()->where('slug', 'gabija')->firstOrFail();
            $unit = Unit::query()->updateOrCreate(
                ['slug' => 'mano-diena'],
                [
                    'language_id' => $language->id,
                    'title' => 'Mano diena',
                    'description' => 'A practical unit for simple daily routines, common present-tense verbs, places, and evening activities.',
                    'cefr_level' => 'a1',
                    'status' => ContentStatus::Published,
                    'sort_order' => 70,
                    'published_at' => now(),
                ],
            );

            $this->syncTranslations($unit, [
                'title' => ['en' => 'My Day', 'lt' => 'Mano diena'],
                'description' => [
                    'en' => 'A practical unit for simple daily routines, common present-tense verbs, places, and evening activities.',
                    'lt' => 'Praktiškas skyrius apie paprastą dienos rutiną, dažnus esamojo laiko veiksmažodžius, vietas ir vakaro veiklas.',
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
                'slug' => 'ryte',
                'title' => 'Ryte',
                'title_en' => 'In the Morning',
                'subtitle' => 'Say what you do in the morning',
                'subtitle_lt' => 'Pasakykite, ką darote ryte',
                'description' => 'Say one simple morning action.',
                'description_lt' => 'Pasakykite vieną paprastą rytinį veiksmą.',
                'emoji' => '🌅',
                'tone' => 'primary',
                'sort_order' => 61,
                'scene_slug' => 'rytas',
                'goal_slug' => 'say-morning-action',
                'goal_label' => 'Say a morning action',
                'goal_label_lt' => 'Pasakykite rytinį veiksmą',
                'goal_intent' => 'The learner says one simple thing they do in the morning.',
                'goal_intent_lt' => 'Mokinys pasako vieną paprastą dalyką, kurį daro ryte.',
                'example' => 'Ryte geriu kavą.',
                'openings' => ['Ką jūs darote ryte?', 'Pasakykite vieną dalyką apie rytą.'],
                'replies' => ['Gerai. Tai aiški rytinė frazė.', 'Puiku. Rytą supratau.'],
                'note' => [
                    'en' => [
                        'This lesson starts daily routine phrases.',
                        'Useful phrases:',
                        '- Ryte geriu kavą. = In the morning I drink coffee.',
                        '- Ryte valgau pusryčius. = In the morning I eat breakfast.',
                        '- Ryte einu į darbą. = In the morning I go to work.',
                        '- Ryte mokausi. = In the morning I study.',
                        'Tiny grammar: ryte means in the morning. Put it at the start for a simple sentence.',
                    ],
                    'lt' => [
                        'Ši pamoka pradeda dienos rutinos frazes.',
                        'Naudingos frazės:',
                        '- Ryte geriu kavą.',
                        '- Ryte valgau pusryčius.',
                        '- Ryte einu į darbą.',
                        '- Ryte mokausi.',
                        'Maža gramatikos pastaba: ryte reiškia „in the morning“. Dėkite žodį sakinio pradžioje.',
                    ],
                ],
            ]),
            $this->scenario([
                'slug' => 'kada-keliates',
                'title' => 'Kada keliasi?',
                'title_en' => 'When Do You Get Up?',
                'subtitle' => 'Say a simple wake-up time',
                'subtitle_lt' => 'Pasakykite paprastą kėlimosi laiką',
                'description' => 'Say when you get up using a simple hour.',
                'description_lt' => 'Pasakykite, kada keliatės, vartodami paprastą valandą.',
                'emoji' => '⏰',
                'tone' => 'amber',
                'sort_order' => 62,
                'scene_slug' => 'kelimasis',
                'goal_slug' => 'say-wake-time',
                'goal_label' => 'Say when you get up',
                'goal_label_lt' => 'Pasakykite, kada keliatės',
                'goal_intent' => 'The learner says when they get up using a simple time phrase.',
                'goal_intent_lt' => 'Mokinys pasako, kada keliasi, vartodamas paprastą laiko frazę.',
                'example' => 'Keliuosi septintą valandą.',
                'openings' => ['Kada jūs keliatės?', 'Kelintą valandą keliatės?'],
                'replies' => ['Gerai. Laikas aiškus.', 'Puiku. Užrašiau laiką.'],
                'note' => [
                    'en' => [
                        'This lesson connects routine and time.',
                        'Useful phrases:',
                        '- Kada jūs keliatės? = When do you get up?',
                        '- Keliuosi septintą valandą. = I get up at seven o’clock.',
                        '- Keliuosi aštuntą valandą. = I get up at eight o’clock.',
                        '- Anksti. = Early.',
                        'Focus: use one full hour. That is enough for A1.',
                    ],
                    'lt' => [
                        'Ši pamoka sujungia rutiną ir laiką.',
                        'Naudingos frazės:',
                        '- Kada jūs keliatės?',
                        '- Keliuosi septintą valandą.',
                        '- Keliuosi aštuntą valandą.',
                        '- Anksti.',
                        'Svarbu: vartokite vieną pilną valandą. A1 lygiui to pakanka.',
                    ],
                ],
            ]),
            $this->scenario([
                'slug' => 'kur-einate',
                'title' => 'Kur einate?',
                'title_en' => 'Where Are You Going?',
                'subtitle' => 'Say where you go',
                'subtitle_lt' => 'Pasakykite, kur einate',
                'description' => 'Say that you go to work, school, home, or a cafe.',
                'description_lt' => 'Pasakykite, kad einate į darbą, mokyklą, namo arba į kavinę.',
                'emoji' => '🚶',
                'tone' => 'mint',
                'sort_order' => 63,
                'scene_slug' => 'kelias',
                'goal_slug' => 'say-where-going',
                'goal_label' => 'Say where you go',
                'goal_label_lt' => 'Pasakykite, kur einate',
                'goal_intent' => 'The learner says where they are going using a simple destination phrase.',
                'goal_intent_lt' => 'Mokinys pasako, kur eina, vartodamas paprastą krypties frazę.',
                'example' => 'Einu į darbą.',
                'openings' => ['Kur jūs einate?', 'Pasakykite, kur einate.'],
                'replies' => ['Gerai. Kryptis aiški.', 'Puiku. Supratau, kur einate.'],
                'note' => [
                    'en' => [
                        'This lesson practises going to a place.',
                        'Useful phrases:',
                        '- Einu į darbą. = I am going to work.',
                        '- Einu į mokyklą. = I am going to school.',
                        '- Einu į kavinę. = I am going to a cafe.',
                        '- Einu namo. = I am going home.',
                        'Tiny grammar: į means to. Namo already means homeward/to home.',
                    ],
                    'lt' => [
                        'Ši pamoka praktikuoja ėjimą į vietą.',
                        'Naudingos frazės:',
                        '- Einu į darbą.',
                        '- Einu į mokyklą.',
                        '- Einu į kavinę.',
                        '- Einu namo.',
                        'Maža gramatikos pastaba: į reiškia kryptį. Namo jau reiškia kryptį į namus.',
                    ],
                ],
            ]),
            $this->scenario([
                'slug' => 'ka-veikiate',
                'title' => 'Ką veikiate?',
                'title_en' => 'What Are You Doing?',
                'subtitle' => 'Say one common activity',
                'subtitle_lt' => 'Pasakykite vieną dažną veiklą',
                'description' => 'Say whether you work, study, rest, read, or eat.',
                'description_lt' => 'Pasakykite, ar dirbate, mokotės, ilsitės, skaitote arba valgote.',
                'emoji' => '📘',
                'tone' => 'sky',
                'sort_order' => 64,
                'scene_slug' => 'veikla',
                'goal_slug' => 'say-current-activity',
                'goal_label' => 'Say what you do',
                'goal_label_lt' => 'Pasakykite, ką veikiate',
                'goal_intent' => 'The learner says one simple current or usual activity.',
                'goal_intent_lt' => 'Mokinys pasako vieną paprastą dabartinę arba įprastą veiklą.',
                'example' => 'Aš dirbu.',
                'openings' => ['Ką jūs veikiate?', 'Pasakykite vieną veiklą.'],
                'replies' => ['Aišku. Tai naudinga frazė.', 'Gerai. Atsakymas suprantamas.'],
                'note' => [
                    'en' => [
                        'This lesson gives you common everyday verbs.',
                        'Useful phrases:',
                        '- Aš dirbu. = I work.',
                        '- Aš mokausi. = I study.',
                        '- Aš ilsiuosi. = I rest.',
                        '- Aš skaitau. = I read.',
                        '- Aš valgau. = I eat.',
                        'Goal: say one activity clearly.',
                    ],
                    'lt' => [
                        'Ši pamoka duoda dažnus kasdienius veiksmažodžius.',
                        'Naudingos frazės:',
                        '- Aš dirbu.',
                        '- Aš mokausi.',
                        '- Aš ilsiuosi.',
                        '- Aš skaitau.',
                        '- Aš valgau.',
                        'Tikslas: aiškiai pasakykite vieną veiklą.',
                    ],
                ],
            ]),
            $this->scenario([
                'slug' => 'vakare',
                'title' => 'Vakare',
                'title_en' => 'In the Evening',
                'subtitle' => 'Say what you do later',
                'subtitle_lt' => 'Pasakykite, ką darote vėliau',
                'description' => 'Say one simple evening activity.',
                'description_lt' => 'Pasakykite vieną paprastą vakaro veiklą.',
                'emoji' => '🌙',
                'tone' => 'berry',
                'sort_order' => 65,
                'scene_slug' => 'vakaras',
                'goal_slug' => 'say-evening-activity',
                'goal_label' => 'Say an evening activity',
                'goal_label_lt' => 'Pasakykite vakaro veiklą',
                'goal_intent' => 'The learner says one thing they do in the evening.',
                'goal_intent_lt' => 'Mokinys pasako vieną dalyką, kurį daro vakare.',
                'example' => 'Vakare ilsiuosi.',
                'openings' => ['Ką darote vakare?', 'O vakare ką veikiate?'],
                'replies' => ['Puiku. Vakaro frazė aiški.', 'Gerai. Skamba natūraliai.'],
                'note' => [
                    'en' => [
                        'This lesson finishes the simple day with evening phrases.',
                        'Useful phrases:',
                        '- Vakare ilsiuosi. = In the evening I rest.',
                        '- Vakare skaitau. = In the evening I read.',
                        '- Vakare žiūriu filmą. = In the evening I watch a film.',
                        '- Vakare einu namo. = In the evening I go home.',
                        'Tiny grammar: vakare means in the evening.',
                    ],
                    'lt' => [
                        'Ši pamoka užbaigia paprastą dieną vakaro frazėmis.',
                        'Naudingos frazės:',
                        '- Vakare ilsiuosi.',
                        '- Vakare skaitau.',
                        '- Vakare žiūriu filmą.',
                        '- Vakare einu namo.',
                        'Maža gramatikos pastaba: vakare reiškia „in the evening“.',
                    ],
                ],
            ]),
            [
                'slug' => 'mano-dienos-pasakojimas',
                'title' => 'Mano dienos pasakojimas',
                'subtitle' => 'Put your day together',
                'description' => 'Say what you do in the morning, where you go, and what you do in the evening.',
                'emoji' => '🗣️',
                'tone' => 'primary',
                'sort_order' => 66,
                'start_scene' => 'rytas',
                'translations' => [
                    'title' => ['en' => 'Talking About My Day', 'lt' => 'Mano dienos pasakojimas'],
                    'subtitle' => ['en' => 'Put your day together', 'lt' => 'Sujunkite savo dieną'],
                    'description' => ['en' => 'Say what you do in the morning, where you go, and what you do in the evening.', 'lt' => 'Pasakykite, ką darote ryte, kur einate ir ką darote vakare.'],
                ],
                'note' => [
                    'title' => 'Before: Talking About My Day',
                    'body' => implode("\n\n", [
                        'This capstone combines the routine phrases from the unit.',
                        'Useful flow:',
                        '- Ryte geriu kavą. = In the morning I drink coffee.',
                        '- Einu į darbą. = I go to work.',
                        '- Aš dirbu. = I work.',
                        '- Vakare ilsiuosi. = In the evening I rest.',
                        'Goal: use two or three short sentences. Simple is good.',
                    ]),
                    'translations' => [
                        'title' => ['en' => 'Before: Talking About My Day', 'lt' => 'Prieš scenarijų: Mano dienos pasakojimas'],
                        'body' => ['en' => implode("\n\n", [
                            'This capstone combines the routine phrases from the unit.',
                            'Useful flow:',
                            '- Ryte geriu kavą. = In the morning I drink coffee.',
                            '- Einu į darbą. = I go to work.',
                            '- Aš dirbu. = I work.',
                            '- Vakare ilsiuosi. = In the evening I rest.',
                            'Goal: use two or three short sentences. Simple is good.',
                        ]), 'lt' => implode("\n\n", [
                            'Šis pakartojimo scenarijus sujungia rutinos frazes iš šio skyriaus.',
                            'Naudinga seka:',
                            '- Ryte geriu kavą.',
                            '- Einu į darbą.',
                            '- Aš dirbu.',
                            '- Vakare ilsiuosi.',
                            'Tikslas: vartokite du ar tris trumpus sakinius. Paprastai yra gerai.',
                        ])],
                    ],
                ],
                'scenes' => [
                    $this->capstoneScene('rytas', 'Morning', 'Rytas', 'Gabija asks about the learner’s morning.', 'Gabija klausia apie mokinio rytą.', ['Papasakokite apie savo rytą.', 'Ką darote ryte?'], ['Gerai. Pradžia aiški.', 'Puiku. Rytą supratau.'], 'capstone-morning', 'Say your morning routine', 'Pasakykite rytinę rutiną', 'The learner says one simple thing they do in the morning.', 'Mokinys pasako vieną paprastą dalyką, kurį daro ryte.', 'Ryte geriu kavą.', 'diena'),
                    $this->capstoneScene('diena', 'Day activity', 'Dienos veikla', 'Gabija asks where the learner goes or what they do during the day.', 'Gabija klausia, kur mokinys eina arba ką veikia dieną.', ['Kur einate dieną?', 'Ką veikiate dieną?'], ['Gerai. Dienos veikla aiški.', 'Puiku. Tai suprantama.'], 'capstone-day', 'Say your day activity', 'Pasakykite dienos veiklą', 'The learner says where they go or what they do during the day.', 'Mokinys pasako, kur eina arba ką veikia dieną.', 'Einu į darbą.', 'vakaras'),
                    $this->capstoneScene('vakaras', 'Evening', 'Vakaras', 'Gabija asks how the learner ends the day.', 'Gabija klausia, kaip mokinys užbaigia dieną.', ['O ką darote vakare?', 'Kaip baigiasi jūsų diena?'], ['Labai gerai. Tai aiškus dienos pasakojimas.', 'Puiku. Jūsų dieną supratau.'], 'capstone-evening', 'Say your evening activity', 'Pasakykite vakaro veiklą', 'The learner says one simple evening activity.', 'Mokinys pasako vieną paprastą vakaro veiklą.', 'Vakare ilsiuosi.', null),
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
            'Ką jūs darote ryte?' => 'What do you do in the morning?',
            'Pasakykite vieną dalyką apie rytą.' => 'Say one thing about the morning.',
            'Gerai. Tai aiški rytinė frazė.' => 'Good. That is a clear morning phrase.',
            'Puiku. Rytą supratau.' => 'Great. I understood the morning.',
            'Kada jūs keliatės?' => 'When do you get up?',
            'Kelintą valandą keliatės?' => 'At what time do you get up?',
            'Gerai. Laikas aiškus.' => 'Good. The time is clear.',
            'Puiku. Užrašiau laiką.' => 'Great. I wrote down the time.',
            'Kur jūs einate?' => 'Where are you going?',
            'Pasakykite, kur einate.' => 'Say where you are going.',
            'Gerai. Kryptis aiški.' => 'Good. The direction is clear.',
            'Puiku. Supratau, kur einate.' => 'Great. I understood where you are going.',
            'Ką jūs veikiate?' => 'What are you doing?',
            'Pasakykite vieną veiklą.' => 'Say one activity.',
            'Aišku. Tai naudinga frazė.' => 'Clear. That is a useful phrase.',
            'Gerai. Atsakymas suprantamas.' => 'Good. The answer is understandable.',
            'Ką darote vakare?' => 'What do you do in the evening?',
            'O vakare ką veikiate?' => 'And what do you do in the evening?',
            'Puiku. Vakaro frazė aiški.' => 'Great. The evening phrase is clear.',
            'Gerai. Skamba natūraliai.' => 'Good. It sounds natural.',
            'Papasakokite apie savo rytą.' => 'Tell me about your morning.',
            'Gerai. Pradžia aiški.' => 'Good. The beginning is clear.',
            'Kur einate dieną?' => 'Where do you go during the day?',
            'Ką veikiate dieną?' => 'What do you do during the day?',
            'Gerai. Dienos veikla aiški.' => 'Good. The day activity is clear.',
            'Puiku. Tai suprantama.' => 'Great. That is understandable.',
            'O ką darote vakare?' => 'And what do you do in the evening?',
            'Kaip baigiasi jūsų diena?' => 'How does your day end?',
            'Labai gerai. Tai aiškus dienos pasakojimas.' => 'Very good. That is a clear description of the day.',
            'Puiku. Jūsų dieną supratau.' => 'Great. I understood your day.',
            default => $line,
        };
    }
}
