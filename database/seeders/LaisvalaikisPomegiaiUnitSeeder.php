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

class LaisvalaikisPomegiaiUnitSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $language = Language::query()->where('code', 'lt')->firstOrFail();
            $character = Character::query()->where('slug', 'gabija')->firstOrFail();
            $unit = Unit::query()->updateOrCreate(
                ['slug' => 'laisvalaikis-ir-pomegiai'],
                [
                    'language_id' => $language->id,
                    'title' => 'Laisvalaikis ir pomėgiai',
                    'description' => 'A practical unit for saying what you do in free time, what you like, what you dislike, and when you have time.',
                    'cefr_level' => 'a1',
                    'status' => ContentStatus::Published,
                    'sort_order' => 130,
                    'published_at' => now(),
                ],
            );

            $this->syncTranslations($unit, [
                'title' => ['en' => 'Free Time and Hobbies', 'lt' => 'Laisvalaikis ir pomėgiai'],
                'description' => [
                    'en' => 'A practical unit for saying what you do in free time, what you like, what you dislike, and when you have time.',
                    'lt' => 'Praktiškas skyrius apie tai, ką veikiate laisvalaikiu, kas jums patinka, kas nepatinka ir kada turite laiko.',
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

    private function deleteRemovedScenes(Scenario $scenario, array $sceneData): void
    {
        $scenario->scenes()
            ->whereNotIn('slug', collect($sceneData)->pluck('slug')->all())
            ->get()
            ->each
            ->delete();
    }

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
                'slug' => 'ka-veikiate-laisvalaikiu',
                'title' => 'Ką veikiate laisvalaikiu?',
                'title_en' => 'What Do You Do in Free Time?',
                'subtitle' => 'Say one free-time activity',
                'subtitle_lt' => 'Pasakykite vieną laisvalaikio veiklą',
                'description' => 'Say what you do in your free time.',
                'description_lt' => 'Pasakykite, ką veikiate laisvalaikiu.',
                'emoji' => '🎒',
                'tone' => 'primary',
                'sort_order' => 131,
                'scene_slug' => 'veikla',
                'goal_slug' => 'say-free-time-activity',
                'goal_label' => 'Say a free-time activity',
                'goal_label_lt' => 'Pasakykite laisvalaikio veiklą',
                'goal_intent' => 'The learner says one simple activity they do in free time.',
                'goal_intent_lt' => 'Mokinys pasako vieną paprastą veiklą, kurią veikia laisvalaikiu.',
                'example' => 'Laisvalaikiu skaitau.',
                'openings' => ['Ką veikiate laisvalaikiu?', 'Pasakykite, ką veikiate laisvalaikiu.'],
                'replies' => ['Gerai. Tai gera veikla.', 'Puiku. Supratau jūsų veiklą.'],
                'note' => [
                    'en' => [
                        'This lesson starts free-time conversation with one activity.',
                        'Useful phrases:',
                        '- Ką veikiate laisvalaikiu? = What do you do in free time?',
                        '- Laisvalaikiu skaitau. = I read in my free time.',
                        '- Laisvalaikiu sportuoju. = I exercise in my free time.',
                        '- Laisvalaikiu klausausi muzikos. = I listen to music in my free time.',
                        'Focus: laisvalaikiu means in free time.',
                    ],
                    'lt' => [
                        'Ši pamoka pradeda laisvalaikio pokalbį viena veikla.',
                        'Naudingos frazės:',
                        '- Ką veikiate laisvalaikiu?',
                        '- Laisvalaikiu skaitau.',
                        '- Laisvalaikiu sportuoju.',
                        '- Laisvalaikiu klausausi muzikos.',
                        'Svarbu: laisvalaikiu reiškia in free time.',
                    ],
                ],
            ]),
            $this->scenario([
                'slug' => 'man-patinka',
                'title' => 'Man patinka',
                'title_en' => 'I Like',
                'subtitle' => 'Say what you like',
                'subtitle_lt' => 'Pasakykite, kas jums patinka',
                'description' => 'Say one hobby or activity you like.',
                'description_lt' => 'Pasakykite vieną pomėgį arba veiklą, kuri jums patinka.',
                'emoji' => '⭐',
                'tone' => 'mint',
                'sort_order' => 132,
                'scene_slug' => 'patinka',
                'goal_slug' => 'say-like',
                'goal_label' => 'Say what you like',
                'goal_label_lt' => 'Pasakykite, kas patinka',
                'goal_intent' => 'The learner says one hobby or activity they like.',
                'goal_intent_lt' => 'Mokinys pasako vieną pomėgį arba veiklą, kuri patinka.',
                'example' => 'Man patinka muzika.',
                'openings' => ['Kas jums patinka?', 'Kokia veikla jums patinka?'],
                'replies' => ['Puiku. Tai aišku.', 'Gerai. Ačiū, kad pasakėte.'],
                'note' => [
                    'en' => [
                        'This lesson practises man patinka.',
                        'Useful phrases:',
                        '- Man patinka muzika. = I like music.',
                        '- Man patinka sportas. = I like sport.',
                        '- Man patinka kinas. = I like cinema.',
                        '- Man patinka skaityti. = I like to read.',
                        'Focus: man patinka means I like.',
                    ],
                    'lt' => [
                        'Ši pamoka praktikuoja man patinka.',
                        'Naudingos frazės:',
                        '- Man patinka muzika.',
                        '- Man patinka sportas.',
                        '- Man patinka kinas.',
                        '- Man patinka skaityti.',
                        'Svarbu: man patinka reiškia I like.',
                    ],
                ],
            ]),
            $this->scenario([
                'slug' => 'man-nepatinka',
                'title' => 'Man nepatinka',
                'title_en' => 'I Do Not Like',
                'subtitle' => 'Say what you do not like',
                'subtitle_lt' => 'Pasakykite, kas jums nepatinka',
                'description' => 'Say one activity you do not like.',
                'description_lt' => 'Pasakykite vieną veiklą, kuri jums nepatinka.',
                'emoji' => '➖',
                'tone' => 'sky',
                'sort_order' => 133,
                'scene_slug' => 'nepatinka',
                'goal_slug' => 'say-dislike',
                'goal_label' => 'Say what you do not like',
                'goal_label_lt' => 'Pasakykite, kas nepatinka',
                'goal_intent' => 'The learner says one activity they do not like.',
                'goal_intent_lt' => 'Mokinys pasako vieną veiklą, kuri nepatinka.',
                'example' => 'Man nepatinka bėgioti.',
                'openings' => ['Kas jums nepatinka?', 'Ar yra veikla, kuri nepatinka?'],
                'replies' => ['Gerai. Supratau.', 'Ačiū. Tai aišku.'],
                'note' => [
                    'en' => [
                        'This lesson adds a simple negative preference.',
                        'Useful phrases:',
                        '- Man nepatinka bėgioti. = I do not like running.',
                        '- Man nepatinka futbolas. = I do not like football.',
                        '- Man nepatinka triukšmas. = I do not like noise.',
                        '- Ne, man nepatinka. = No, I do not like it.',
                        'Focus: nepatinka means do not like.',
                    ],
                    'lt' => [
                        'Ši pamoka prideda paprastą neigiamą pasirinkimą.',
                        'Naudingos frazės:',
                        '- Man nepatinka bėgioti.',
                        '- Man nepatinka futbolas.',
                        '- Man nepatinka triukšmas.',
                        '- Ne, man nepatinka.',
                        'Svarbu: nepatinka reiškia do not like.',
                    ],
                ],
            ]),
            $this->scenario([
                'slug' => 'sportas-ar-muzika',
                'title' => 'Sportas ar muzika?',
                'title_en' => 'Sport or Music?',
                'subtitle' => 'Choose between two hobbies',
                'subtitle_lt' => 'Pasirinkite tarp dviejų pomėgių',
                'description' => 'Choose one hobby and say why simply.',
                'description_lt' => 'Pasirinkite vieną pomėgį ir paprastai pasakykite kodėl.',
                'emoji' => '🎧',
                'tone' => 'amber',
                'sort_order' => 134,
                'scene_slug' => 'pasirinkimas',
                'goal_slug' => 'choose-hobby',
                'goal_label' => 'Choose a hobby',
                'goal_label_lt' => 'Pasirinkite pomėgį',
                'goal_intent' => 'The learner chooses one hobby or says which option they prefer.',
                'goal_intent_lt' => 'Mokinys pasirenka vieną pomėgį arba pasako, kuri galimybė labiau patinka.',
                'example' => 'Man labiau patinka muzika.',
                'openings' => ['Kas jums labiau patinka: sportas ar muzika?', 'Ką renkatės: sportą ar muziką?'],
                'replies' => ['Gerai. Pasirinkimas aiškus.', 'Puiku. Supratau jūsų pasirinkimą.'],
                'note' => [
                    'en' => [
                        'This lesson practises choosing between two simple hobbies.',
                        'Useful phrases:',
                        '- Sportas ar muzika? = Sport or music?',
                        '- Man labiau patinka muzika. = I prefer music.',
                        '- Man labiau patinka sportas. = I prefer sport.',
                        '- Aš renkuosi muziką. = I choose music.',
                        'Focus: labiau patinka means prefer.',
                    ],
                    'lt' => [
                        'Ši pamoka praktikuoja pasirinkimą tarp dviejų paprastų pomėgių.',
                        'Naudingos frazės:',
                        '- Sportas ar muzika?',
                        '- Man labiau patinka muzika.',
                        '- Man labiau patinka sportas.',
                        '- Aš renkuosi muziką.',
                        'Svarbu: labiau patinka reiškia prefer.',
                    ],
                ],
            ]),
            $this->scenario([
                'slug' => 'kada-turite-laiko',
                'title' => 'Kada turite laiko?',
                'title_en' => 'When Do You Have Time?',
                'subtitle' => 'Say when you are free',
                'subtitle_lt' => 'Pasakykite, kada turite laiko',
                'description' => 'Say when you have free time.',
                'description_lt' => 'Pasakykite, kada turite laiko.',
                'emoji' => '🕓',
                'tone' => 'berry',
                'sort_order' => 135,
                'scene_slug' => 'laikas',
                'goal_slug' => 'say-free-time',
                'goal_label' => 'Say when you have time',
                'goal_label_lt' => 'Pasakykite, kada turite laiko',
                'goal_intent' => 'The learner says when they have free time.',
                'goal_intent_lt' => 'Mokinys pasako, kada turi laiko.',
                'example' => 'Turiu laiko vakare.',
                'openings' => ['Kada turite laiko?', 'Kada esate laisvi?'],
                'replies' => ['Gerai. Laikas aiškus.', 'Puiku. Supratau laiką.'],
                'note' => [
                    'en' => [
                        'This lesson connects hobbies with time.',
                        'Useful phrases:',
                        '- Kada turite laiko? = When do you have time?',
                        '- Turiu laiko vakare. = I have time in the evening.',
                        '- Turiu laiko savaitgalį. = I have time on the weekend.',
                        '- Neturiu laiko šiandien. = I do not have time today.',
                        'Focus: turiu laiko means I have time.',
                    ],
                    'lt' => [
                        'Ši pamoka sujungia pomėgius su laiku.',
                        'Naudingos frazės:',
                        '- Kada turite laiko?',
                        '- Turiu laiko vakare.',
                        '- Turiu laiko savaitgalį.',
                        '- Neturiu laiko šiandien.',
                        'Svarbu: turiu laiko reiškia I have time.',
                    ],
                ],
            ]),
            [
                'slug' => 'laisvalaikio-pokalbis',
                'title' => 'Laisvalaikio pokalbis',
                'subtitle' => 'Talk about hobbies and time',
                'description' => 'Say what you do, what you like, and when you have time.',
                'emoji' => '🎲',
                'tone' => 'primary',
                'sort_order' => 136,
                'start_scene' => 'veikla',
                'translations' => [
                    'title' => ['en' => 'Free-Time Conversation', 'lt' => 'Laisvalaikio pokalbis'],
                    'subtitle' => ['en' => 'Talk about hobbies and time', 'lt' => 'Kalbėkite apie pomėgius ir laiką'],
                    'description' => ['en' => 'Say what you do, what you like, and when you have time.', 'lt' => 'Pasakykite, ką veikiate, kas patinka ir kada turite laiko.'],
                ],
                'note' => [
                    'title' => 'Before: Free-Time Conversation',
                    'body' => implode("\n\n", [
                        'This capstone combines free-time and preference phrases.',
                        'Useful flow:',
                        '- Laisvalaikiu skaitau. = I read in my free time.',
                        '- Man patinka muzika. = I like music.',
                        '- Man nepatinka bėgioti. = I do not like running.',
                        '- Turiu laiko vakare. = I have time in the evening.',
                        'Goal: hold a short social conversation about free time.',
                    ]),
                    'translations' => [
                        'title' => ['en' => 'Before: Free-Time Conversation', 'lt' => 'Prieš scenarijų: Laisvalaikio pokalbis'],
                        'body' => ['en' => implode("\n\n", [
                            'This capstone combines free-time and preference phrases.',
                            'Useful flow:',
                            '- Laisvalaikiu skaitau. = I read in my free time.',
                            '- Man patinka muzika. = I like music.',
                            '- Man nepatinka bėgioti. = I do not like running.',
                            '- Turiu laiko vakare. = I have time in the evening.',
                            'Goal: hold a short social conversation about free time.',
                        ]), 'lt' => implode("\n\n", [
                            'Šis pakartojimo scenarijus sujungia laisvalaikio ir pasirinkimo frazes.',
                            'Naudinga seka:',
                            '- Laisvalaikiu skaitau.',
                            '- Man patinka muzika.',
                            '- Man nepatinka bėgioti.',
                            '- Turiu laiko vakare.',
                            'Tikslas: palaikykite trumpą socialinį pokalbį apie laisvalaikį.',
                        ])],
                    ],
                ],
                'scenes' => [
                    $this->capstoneScene('veikla', 'Activity', 'Veikla', 'Gabija asks what the learner does in free time.', 'Gabija klausia, ką mokinys veikia laisvalaikiu.', ['Ką veikiate laisvalaikiu?', 'Kokia jūsų laisvalaikio veikla?'], ['Gerai. Tai aišku.', 'Puiku. Supratau veiklą.'], 'capstone-free-time-activity', 'Say a free-time activity', 'Pasakykite laisvalaikio veiklą', 'The learner says one free-time activity.', 'Mokinys pasako vieną laisvalaikio veiklą.', 'Laisvalaikiu skaitau.', 'patinka'),
                    $this->capstoneScene('patinka', 'Preference', 'Pomėgis', 'Gabija asks what the learner likes.', 'Gabija klausia, kas mokiniui patinka.', ['Kas jums patinka?', 'Kokia veikla jums patinka?'], ['Gerai. Pomėgis aiškus.', 'Puiku. Ačiū, kad pasakėte.'], 'capstone-like', 'Say what you like', 'Pasakykite, kas patinka', 'The learner says one hobby or activity they like.', 'Mokinys pasako vieną pomėgį arba veiklą, kuri patinka.', 'Man patinka muzika.', 'laikas'),
                    $this->capstoneScene('laikas', 'Time', 'Laikas', 'Gabija asks when the learner has time.', 'Gabija klausia, kada mokinys turi laiko.', ['Kada turite laiko?', 'Kada esate laisvi?'], ['Gerai. Laikas aiškus.', 'Ačiū. Dabar suprantu.'], 'capstone-free-time', 'Say when you have time', 'Pasakykite, kada turite laiko', 'The learner says when they have free time.', 'Mokinys pasako, kada turi laiko.', 'Turiu laiko vakare.', null),
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
            'Ką veikiate laisvalaikiu?' => 'What do you do in your free time?',
            'Pasakykite, ką veikiate laisvalaikiu.' => 'Say what you do in your free time.',
            'Gerai. Tai gera veikla.' => 'Good. That is a good activity.',
            'Puiku. Supratau jūsų veiklą.' => 'Great. I understood your activity.',
            'Kas jums patinka?' => 'What do you like?',
            'Kokia veikla jums patinka?' => 'What activity do you like?',
            'Puiku. Tai aišku.' => 'Great. That is clear.',
            'Gerai. Ačiū, kad pasakėte.' => 'Good. Thank you for saying it.',
            'Kas jums nepatinka?' => 'What do you not like?',
            'Ar yra veikla, kuri nepatinka?' => 'Is there an activity you do not like?',
            'Gerai. Supratau.' => 'Good. I understand.',
            'Ačiū. Tai aišku.' => 'Thank you. That is clear.',
            'Kas jums labiau patinka: sportas ar muzika?' => 'What do you prefer: sport or music?',
            'Ką renkatės: sportą ar muziką?' => 'What do you choose: sport or music?',
            'Gerai. Pasirinkimas aiškus.' => 'Good. The choice is clear.',
            'Puiku. Supratau jūsų pasirinkimą.' => 'Great. I understood your choice.',
            'Kada turite laiko?' => 'When do you have time?',
            'Kada esate laisvi?' => 'When are you free?',
            'Gerai. Laikas aiškus.' => 'Good. The time is clear.',
            'Puiku. Supratau laiką.' => 'Great. I understood the time.',
            'Kokia jūsų laisvalaikio veikla?' => 'What is your free-time activity?',
            'Gerai. Tai aišku.' => 'Good. That is clear.',
            'Puiku. Supratau veiklą.' => 'Great. I understood the activity.',
            'Gerai. Pomėgis aiškus.' => 'Good. The hobby is clear.',
            'Puiku. Ačiū, kad pasakėte.' => 'Great. Thank you for saying it.',
            'Ačiū. Dabar suprantu.' => 'Thank you. Now I understand.',
            default => $line,
        };
    }
}
