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

class GebejimaiPoreikiaiUnitSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $language = Language::query()->where('code', 'lt')->firstOrFail();
            $character = Character::query()->where('slug', 'gabija')->firstOrFail();
            $unit = Unit::query()->updateOrCreate(
                ['slug' => 'gebejimai-ir-poreikiai'],
                [
                    'language_id' => $language->id,
                    'title' => 'Gebėjimai ir poreikiai',
                    'description' => 'A practical unit for saying what you can do, cannot do, want, need, and must do.',
                    'cefr_level' => 'a1',
                    'status' => ContentStatus::Published,
                    'sort_order' => 150,
                    'published_at' => now(),
                ],
            );

            $this->syncTranslations($unit, [
                'title' => ['en' => 'Abilities and Needs', 'lt' => 'Gebėjimai ir poreikiai'],
                'description' => [
                    'en' => 'A practical unit for saying what you can do, cannot do, want, need, and must do.',
                    'lt' => 'Praktiškas skyrius apie tai, ką galite, negalite, norite, turite ir privalote daryti.',
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
                'slug' => 'as-galiu',
                'title' => 'Aš galiu',
                'title_en' => 'I Can',
                'subtitle' => 'Say what you can do',
                'subtitle_lt' => 'Pasakykite, ką galite daryti',
                'description' => 'Say one simple thing you can do.',
                'description_lt' => 'Pasakykite vieną paprastą dalyką, kurį galite daryti.',
                'emoji' => '✅',
                'tone' => 'primary',
                'sort_order' => 151,
                'scene_slug' => 'gebejimas',
                'goal_slug' => 'say-can',
                'goal_label' => 'Say what you can do',
                'goal_label_lt' => 'Pasakykite, ką galite daryti',
                'goal_intent' => 'The learner says one simple thing they can do.',
                'goal_intent_lt' => 'Mokinys pasako vieną paprastą dalyką, kurį gali daryti.',
                'example' => 'Aš galiu kalbėti lietuviškai.',
                'openings' => ['Ką galite daryti?', 'Pasakykite, ką galite.'],
                'replies' => ['Puiku. Gebėjimas aiškus.', 'Gerai. Supratau, ką galite.'],
                'note' => [
                    'en' => [
                        'This lesson practises galiu.',
                        'Useful phrases:',
                        '- Aš galiu. = I can.',
                        '- Aš galiu kalbėti lietuviškai. = I can speak Lithuanian.',
                        '- Aš galiu skaityti. = I can read.',
                        '- Aš galiu padėti. = I can help.',
                        'Focus: galiu + verb means I can do something.',
                    ],
                    'lt' => [
                        'Ši pamoka praktikuoja galiu.',
                        'Naudingos frazės:',
                        '- Aš galiu.',
                        '- Aš galiu kalbėti lietuviškai.',
                        '- Aš galiu skaityti.',
                        '- Aš galiu padėti.',
                        'Svarbu: galiu + veiksmažodis reiškia I can do something.',
                    ],
                ],
            ]),
            $this->scenario([
                'slug' => 'as-negaliu',
                'title' => 'Aš negaliu',
                'title_en' => 'I Cannot',
                'subtitle' => 'Say what you cannot do',
                'subtitle_lt' => 'Pasakykite, ko negalite daryti',
                'description' => 'Say one simple thing you cannot do.',
                'description_lt' => 'Pasakykite vieną paprastą dalyką, kurio negalite daryti.',
                'emoji' => '⛔',
                'tone' => 'mint',
                'sort_order' => 152,
                'scene_slug' => 'ribojimas',
                'goal_slug' => 'say-cannot',
                'goal_label' => 'Say what you cannot do',
                'goal_label_lt' => 'Pasakykite, ko negalite daryti',
                'goal_intent' => 'The learner says one simple thing they cannot do.',
                'goal_intent_lt' => 'Mokinys pasako vieną paprastą dalyką, kurio negali daryti.',
                'example' => 'Aš negaliu ateiti šiandien.',
                'openings' => ['Ko negalite daryti?', 'Pasakykite, jeigu negalite.'],
                'replies' => ['Suprantu. Viskas gerai.', 'Gerai. Ačiū, kad pasakėte.'],
                'note' => [
                    'en' => [
                        'This lesson adds negaliu.',
                        'Useful phrases:',
                        '- Aš negaliu. = I cannot.',
                        '- Aš negaliu ateiti. = I cannot come.',
                        '- Aš negaliu šiandien. = I cannot today.',
                        '- Atsiprašau, negaliu. = Sorry, I cannot.',
                        'Focus: negaliu is the negative of galiu.',
                    ],
                    'lt' => [
                        'Ši pamoka prideda negaliu.',
                        'Naudingos frazės:',
                        '- Aš negaliu.',
                        '- Aš negaliu ateiti.',
                        '- Aš negaliu šiandien.',
                        '- Atsiprašau, negaliu.',
                        'Svarbu: negaliu yra neigiama galiu forma.',
                    ],
                ],
            ]),
            $this->scenario([
                'slug' => 'as-noriu',
                'title' => 'Aš noriu',
                'title_en' => 'I Want',
                'subtitle' => 'Say what you want',
                'subtitle_lt' => 'Pasakykite, ko norite',
                'description' => 'Say one simple thing you want.',
                'description_lt' => 'Pasakykite vieną paprastą dalyką, kurio norite.',
                'emoji' => '💭',
                'tone' => 'sky',
                'sort_order' => 153,
                'scene_slug' => 'noras',
                'goal_slug' => 'say-want',
                'goal_label' => 'Say what you want',
                'goal_label_lt' => 'Pasakykite, ko norite',
                'goal_intent' => 'The learner says one simple thing they want.',
                'goal_intent_lt' => 'Mokinys pasako vieną paprastą dalyką, kurio nori.',
                'example' => 'Aš noriu vandens.',
                'openings' => ['Ko norite?', 'Pasakykite, ko norite.'],
                'replies' => ['Gerai. Noras aiškus.', 'Puiku. Supratau, ko norite.'],
                'note' => [
                    'en' => [
                        'This lesson practises noriu.',
                        'Useful phrases:',
                        '- Aš noriu vandens. = I want water.',
                        '- Aš noriu kavos. = I want coffee.',
                        '- Aš noriu mokytis. = I want to learn.',
                        '- Ko norite? = What do you want?',
                        'Focus: noriu means I want.',
                    ],
                    'lt' => [
                        'Ši pamoka praktikuoja noriu.',
                        'Naudingos frazės:',
                        '- Aš noriu vandens.',
                        '- Aš noriu kavos.',
                        '- Aš noriu mokytis.',
                        '- Ko norite?',
                        'Svarbu: noriu reiškia I want.',
                    ],
                ],
            ]),
            $this->scenario([
                'slug' => 'man-reikia',
                'title' => 'Man reikia',
                'title_en' => 'I Need',
                'subtitle' => 'Say what you need',
                'subtitle_lt' => 'Pasakykite, ko reikia',
                'description' => 'Say one simple thing you need.',
                'description_lt' => 'Pasakykite vieną paprastą dalyką, kurio reikia.',
                'emoji' => '🧩',
                'tone' => 'amber',
                'sort_order' => 154,
                'scene_slug' => 'poreikis',
                'goal_slug' => 'say-need',
                'goal_label' => 'Say what you need',
                'goal_label_lt' => 'Pasakykite, ko reikia',
                'goal_intent' => 'The learner says one simple thing they need.',
                'goal_intent_lt' => 'Mokinys pasako vieną paprastą dalyką, kurio reikia.',
                'example' => 'Man reikia pagalbos.',
                'openings' => ['Ko jums reikia?', 'Pasakykite, ko reikia.'],
                'replies' => ['Žinoma. Pabandysiu padėti.', 'Gerai. Poreikis aiškus.'],
                'note' => [
                    'en' => [
                        'This lesson practises man reikia.',
                        'Useful phrases:',
                        '- Man reikia pagalbos. = I need help.',
                        '- Man reikia bilieto. = I need a ticket.',
                        '- Man reikia vandens. = I need water.',
                        '- Ko jums reikia? = What do you need?',
                        'Focus: man reikia means I need.',
                    ],
                    'lt' => [
                        'Ši pamoka praktikuoja man reikia.',
                        'Naudingos frazės:',
                        '- Man reikia pagalbos.',
                        '- Man reikia bilieto.',
                        '- Man reikia vandens.',
                        '- Ko jums reikia?',
                        'Svarbu: man reikia reiškia I need.',
                    ],
                ],
            ]),
            $this->scenario([
                'slug' => 'as-turiu',
                'title' => 'Aš turiu',
                'title_en' => 'I Have To',
                'subtitle' => 'Say what you must do',
                'subtitle_lt' => 'Pasakykite, ką turite daryti',
                'description' => 'Say one simple thing you have to do.',
                'description_lt' => 'Pasakykite vieną paprastą dalyką, kurį turite daryti.',
                'emoji' => '📌',
                'tone' => 'berry',
                'sort_order' => 155,
                'scene_slug' => 'pareiga',
                'goal_slug' => 'say-have-to',
                'goal_label' => 'Say what you have to do',
                'goal_label_lt' => 'Pasakykite, ką turite daryti',
                'goal_intent' => 'The learner says one simple thing they have to do.',
                'goal_intent_lt' => 'Mokinys pasako vieną paprastą dalyką, kurį turi daryti.',
                'example' => 'Aš turiu dirbti.',
                'openings' => ['Ką turite daryti?', 'Pasakykite, ką turite daryti.'],
                'replies' => ['Gerai. Pareiga aiški.', 'Suprantu. Ačiū, kad pasakėte.'],
                'note' => [
                    'en' => [
                        'This lesson practises turiu + verb.',
                        'Useful phrases:',
                        '- Aš turiu dirbti. = I have to work.',
                        '- Aš turiu mokytis. = I have to study.',
                        '- Aš turiu eiti. = I have to go.',
                        '- Ką turite daryti? = What do you have to do?',
                        'Focus: turiu can mean I have, but with a verb it can mean I have to.',
                    ],
                    'lt' => [
                        'Ši pamoka praktikuoja turiu + veiksmažodis.',
                        'Naudingos frazės:',
                        '- Aš turiu dirbti.',
                        '- Aš turiu mokytis.',
                        '- Aš turiu eiti.',
                        '- Ką turite daryti?',
                        'Svarbu: turiu gali reikšti I have, bet su veiksmažodžiu gali reikšti I have to.',
                    ],
                ],
            ]),
            [
                'slug' => 'poreikio-pokalbis',
                'title' => 'Poreikio pokalbis',
                'subtitle' => 'Explain what you need and can do',
                'description' => 'Say what you need, what you can or cannot do, and what you have to do.',
                'emoji' => '🗣️',
                'tone' => 'primary',
                'sort_order' => 156,
                'start_scene' => 'poreikis',
                'translations' => [
                    'title' => ['en' => 'Needs Conversation', 'lt' => 'Poreikio pokalbis'],
                    'subtitle' => ['en' => 'Explain what you need and can do', 'lt' => 'Paaiškinkite, ko reikia ir ką galite'],
                    'description' => ['en' => 'Say what you need, what you can or cannot do, and what you have to do.', 'lt' => 'Pasakykite, ko reikia, ką galite arba negalite daryti ir ką turite daryti.'],
                ],
                'note' => [
                    'title' => 'Before: Needs Conversation',
                    'body' => implode("\n\n", [
                        'This capstone combines ability and need phrases.',
                        'Useful flow:',
                        '- Man reikia pagalbos. = I need help.',
                        '- Aš galiu kalbėti lietuviškai. = I can speak Lithuanian.',
                        '- Aš negaliu ateiti šiandien. = I cannot come today.',
                        '- Aš turiu mokytis. = I have to study.',
                        'Goal: explain a simple need, ability, or limitation.',
                    ]),
                    'translations' => [
                        'title' => ['en' => 'Before: Needs Conversation', 'lt' => 'Prieš scenarijų: Poreikio pokalbis'],
                        'body' => ['en' => implode("\n\n", [
                            'This capstone combines ability and need phrases.',
                            'Useful flow:',
                            '- Man reikia pagalbos. = I need help.',
                            '- Aš galiu kalbėti lietuviškai. = I can speak Lithuanian.',
                            '- Aš negaliu ateiti šiandien. = I cannot come today.',
                            '- Aš turiu mokytis. = I have to study.',
                            'Goal: explain a simple need, ability, or limitation.',
                        ]), 'lt' => implode("\n\n", [
                            'Šis pakartojimo scenarijus sujungia gebėjimo ir poreikio frazes.',
                            'Naudinga seka:',
                            '- Man reikia pagalbos.',
                            '- Aš galiu kalbėti lietuviškai.',
                            '- Aš negaliu ateiti šiandien.',
                            '- Aš turiu mokytis.',
                            'Tikslas: paaiškinkite paprastą poreikį, gebėjimą arba ribojimą.',
                        ])],
                    ],
                ],
                'scenes' => [
                    $this->capstoneScene('poreikis', 'Need', 'Poreikis', 'Gabija asks what the learner needs.', 'Gabija klausia, ko mokiniui reikia.', ['Ko jums reikia?', 'Pasakykite, ko reikia.'], ['Gerai. Poreikis aiškus.', 'Žinoma. Supratau.'], 'capstone-need', 'Say what you need', 'Pasakykite, ko reikia', 'The learner says one simple thing they need.', 'Mokinys pasako vieną paprastą dalyką, kurio reikia.', 'Man reikia pagalbos.', 'gebejimas'),
                    $this->capstoneScene('gebejimas', 'Ability', 'Gebėjimas', 'Gabija asks what the learner can do.', 'Gabija klausia, ką mokinys gali daryti.', ['Ką galite daryti?', 'Pasakykite, ką galite.'], ['Puiku. Gebėjimas aiškus.', 'Gerai. Supratau.'], 'capstone-can', 'Say what you can do', 'Pasakykite, ką galite daryti', 'The learner says one simple thing they can do.', 'Mokinys pasako vieną paprastą dalyką, kurį gali daryti.', 'Aš galiu kalbėti lietuviškai.', 'ribojimas'),
                    $this->capstoneScene('ribojimas', 'Limitation', 'Ribojimas', 'Gabija asks what the learner cannot do or must do.', 'Gabija klausia, ko mokinys negali arba ką turi daryti.', ['Ko negalite daryti?', 'Ką turite daryti?'], ['Suprantu. Ačiū, kad pasakėte.', 'Gerai. Viskas aišku.'], 'capstone-limitation', 'Say a limitation or obligation', 'Pasakykite ribojimą arba pareigą', 'The learner says one simple thing they cannot do or have to do.', 'Mokinys pasako vieną paprastą dalyką, kurio negali arba ką turi daryti.', 'Aš negaliu ateiti šiandien.', null),
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
            'Ką galite daryti?' => 'What can you do?',
            'Pasakykite, ką galite.' => 'Say what you can do.',
            'Puiku. Gebėjimas aiškus.' => 'Great. The ability is clear.',
            'Gerai. Supratau, ką galite.' => 'Good. I understood what you can do.',
            'Ko negalite daryti?' => 'What can you not do?',
            'Pasakykite, jeigu negalite.' => 'Say it if you cannot.',
            'Suprantu. Viskas gerai.' => 'I understand. Everything is fine.',
            'Gerai. Ačiū, kad pasakėte.' => 'Good. Thank you for saying it.',
            'Ko norite?' => 'What do you want?',
            'Pasakykite, ko norite.' => 'Say what you want.',
            'Gerai. Noras aiškus.' => 'Good. The want is clear.',
            'Puiku. Supratau, ko norite.' => 'Great. I understood what you want.',
            'Ko jums reikia?' => 'What do you need?',
            'Pasakykite, ko reikia.' => 'Say what is needed.',
            'Žinoma. Pabandysiu padėti.' => 'Of course. I will try to help.',
            'Gerai. Poreikis aiškus.' => 'Good. The need is clear.',
            'Ką turite daryti?' => 'What do you have to do?',
            'Pasakykite, ką turite daryti.' => 'Say what you have to do.',
            'Gerai. Pareiga aiški.' => 'Good. The obligation is clear.',
            'Suprantu. Ačiū, kad pasakėte.' => 'I understand. Thank you for saying it.',
            'Žinoma. Supratau.' => 'Of course. I understand.',
            'Gerai. Supratau.' => 'Good. I understand.',
            'Gerai. Viskas aišku.' => 'Good. Everything is clear.',
            default => $line,
        };
    }
}
