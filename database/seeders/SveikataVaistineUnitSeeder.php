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

class SveikataVaistineUnitSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $language = Language::query()->where('code', 'lt')->firstOrFail();
            $character = Character::query()->where('slug', 'gabija')->firstOrFail();
            $unit = Unit::query()->updateOrCreate(
                ['slug' => 'sveikata-ir-vaistine'],
                [
                    'language_id' => $language->id,
                    'title' => 'Sveikata ir vaistinė',
                    'description' => 'A practical unit for saying how you feel, naming simple symptoms, asking for medicine, usage, and payment.',
                    'cefr_level' => 'a1',
                    'status' => ContentStatus::Published,
                    'sort_order' => 110,
                    'published_at' => now(),
                ],
            );

            $this->syncTranslations($unit, [
                'title' => ['en' => 'Health and Pharmacy', 'lt' => 'Sveikata ir vaistinė'],
                'description' => [
                    'en' => 'A practical unit for saying how you feel, naming simple symptoms, asking for medicine, usage, and payment.',
                    'lt' => 'Praktiškas skyrius apie savijautą, paprastus simptomus, vaistų prašymą, vartojimą ir mokėjimą.',
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
                'slug' => 'kaip-jauciates',
                'title' => 'Kaip jaučiatės?',
                'title_en' => 'How Do You Feel?',
                'subtitle' => 'Say how you feel',
                'subtitle_lt' => 'Pasakykite, kaip jaučiatės',
                'description' => 'Say that you feel well, unwell, or need help.',
                'description_lt' => 'Pasakykite, kad jaučiatės gerai, blogai arba reikia pagalbos.',
                'emoji' => '🩺',
                'tone' => 'primary',
                'sort_order' => 111,
                'scene_slug' => 'savijauta',
                'goal_slug' => 'say-how-feel',
                'goal_label' => 'Say how you feel',
                'goal_label_lt' => 'Pasakykite, kaip jaučiatės',
                'goal_intent' => 'The learner says how they feel or says they need help.',
                'goal_intent_lt' => 'Mokinys pasako, kaip jaučiasi, arba pasako, kad reikia pagalbos.',
                'example' => 'Aš blogai jaučiuosi.',
                'openings' => ['Kaip jaučiatės?', 'Ar jums reikia pagalbos?'],
                'replies' => ['Suprantu. Pabandysiu padėti.', 'Gerai. Pasakykite daugiau.'],
                'note' => [
                    'en' => [
                        'This lesson starts health with simple feeling phrases.',
                        'Useful phrases:',
                        '- Kaip jaučiatės? = How do you feel?',
                        '- Jaučiuosi gerai. = I feel well.',
                        '- Jaučiuosi blogai. = I feel bad.',
                        '- Man reikia pagalbos. = I need help.',
                        'Focus: jaučiuosi means I feel.',
                    ],
                    'lt' => [
                        'Ši pamoka pradeda sveikatos temą paprastomis savijautos frazėmis.',
                        'Naudingos frazės:',
                        '- Kaip jaučiatės?',
                        '- Jaučiuosi gerai.',
                        '- Jaučiuosi blogai.',
                        '- Man reikia pagalbos.',
                        'Svarbu: jaučiuosi reiškia I feel.',
                    ],
                ],
            ]),
            $this->scenario([
                'slug' => 'ka-skauda',
                'title' => 'Ką skauda?',
                'title_en' => 'What Hurts?',
                'subtitle' => 'Name a simple pain',
                'subtitle_lt' => 'Pasakykite paprastą skausmą',
                'description' => 'Say that your head, throat, stomach, or tooth hurts.',
                'description_lt' => 'Pasakykite, kad skauda galvą, gerklę, pilvą arba dantį.',
                'emoji' => '🤒',
                'tone' => 'mint',
                'sort_order' => 112,
                'scene_slug' => 'skausmas',
                'goal_slug' => 'say-pain',
                'goal_label' => 'Say what hurts',
                'goal_label_lt' => 'Pasakykite, ką skauda',
                'goal_intent' => 'The learner names one simple health problem with man skauda.',
                'goal_intent_lt' => 'Mokinys pasako vieną paprastą sveikatos problemą su man skauda.',
                'example' => 'Man skauda galvą.',
                'openings' => ['Ką jums skauda?', 'Kas jums yra?'],
                'replies' => ['Suprantu. Ačiū, kad pasakėte.', 'Gerai. Skausmas aiškus.'],
                'note' => [
                    'en' => [
                        'This lesson practises the most useful A1 health pattern.',
                        'Useful phrases:',
                        '- Man skauda galvą. = My head hurts.',
                        '- Man skauda gerklę. = My throat hurts.',
                        '- Man skauda pilvą. = My stomach hurts.',
                        '- Man skauda dantį. = My tooth hurts.',
                        'Pattern: man skauda + body part.',
                    ],
                    'lt' => [
                        'Ši pamoka praktikuoja naudingiausią A1 sveikatos frazę.',
                        'Naudingos frazės:',
                        '- Man skauda galvą.',
                        '- Man skauda gerklę.',
                        '- Man skauda pilvą.',
                        '- Man skauda dantį.',
                        'Modelis: man skauda + kūno dalis.',
                    ],
                ],
            ]),
            $this->scenario([
                'slug' => 'vaisto-prasymas',
                'title' => 'Vaisto prašymas',
                'title_en' => 'Asking for Medicine',
                'subtitle' => 'Ask for simple medicine',
                'subtitle_lt' => 'Paprašykite paprasto vaisto',
                'description' => 'Ask for medicine for a simple problem.',
                'description_lt' => 'Paprašykite vaisto nuo paprastos problemos.',
                'emoji' => '💊',
                'tone' => 'sky',
                'sort_order' => 113,
                'scene_slug' => 'vaistas',
                'goal_slug' => 'ask-medicine',
                'goal_label' => 'Ask for medicine',
                'goal_label_lt' => 'Paprašykite vaisto',
                'goal_intent' => 'The learner asks for medicine for pain, throat, or a cold.',
                'goal_intent_lt' => 'Mokinys paprašo vaisto nuo skausmo, gerklės arba peršalimo.',
                'example' => 'Prašau vaistų nuo galvos skausmo.',
                'openings' => ['Kokio vaisto reikia?', 'Ko ieškote vaistinėje?'],
                'replies' => ['Žinoma. Galiu pasiūlyti vaistų.', 'Gerai. Pažiūrėkime, kas tinka.'],
                'note' => [
                    'en' => [
                        'This lesson turns a symptom into a simple medicine request.',
                        'Useful phrases:',
                        '- Prašau vaistų. = Medicine, please.',
                        '- Prašau vaistų nuo galvos skausmo. = Medicine for headache, please.',
                        '- Prašau vaistų nuo gerklės skausmo. = Medicine for throat pain, please.',
                        '- Turiu peršalimą. = I have a cold.',
                        'Focus: nuo often means for or against in medicine phrases.',
                    ],
                    'lt' => [
                        'Ši pamoka simptomą paverčia paprastu vaisto prašymu.',
                        'Naudingos frazės:',
                        '- Prašau vaistų.',
                        '- Prašau vaistų nuo galvos skausmo.',
                        '- Prašau vaistų nuo gerklės skausmo.',
                        '- Turiu peršalimą.',
                        'Svarbu: vaistų frazėse nuo dažnai reiškia for arba against.',
                    ],
                ],
            ]),
            $this->scenario([
                'slug' => 'kaip-vartoti',
                'title' => 'Kaip vartoti?',
                'title_en' => 'How Should I Take It?',
                'subtitle' => 'Ask how to use medicine',
                'subtitle_lt' => 'Paklauskite, kaip vartoti vaistą',
                'description' => 'Ask how to take medicine and understand a simple answer.',
                'description_lt' => 'Paklauskite, kaip vartoti vaistą, ir supraskite paprastą atsakymą.',
                'emoji' => '📋',
                'tone' => 'amber',
                'sort_order' => 114,
                'scene_slug' => 'vartojimas',
                'goal_slug' => 'ask-use',
                'goal_label' => 'Ask how to take it',
                'goal_label_lt' => 'Paklauskite, kaip vartoti',
                'goal_intent' => 'The learner asks how to take or use the medicine.',
                'goal_intent_lt' => 'Mokinys paklausia, kaip vartoti vaistą.',
                'example' => 'Kaip vartoti šį vaistą?',
                'openings' => ['Ar norite paklausti, kaip vartoti?', 'Ką dar norite žinoti?'],
                'replies' => ['Vartokite du kartus per dieną.', 'Gerkite ryte ir vakare.'],
                'note' => [
                    'en' => [
                        'This lesson practises medicine-use questions.',
                        'Useful phrases:',
                        '- Kaip vartoti šį vaistą? = How should I take this medicine?',
                        '- Kiek kartų per dieną? = How many times per day?',
                        '- Ryte ir vakare. = In the morning and evening.',
                        '- Du kartus per dieną. = Twice a day.',
                        'A1 goal: ask the question. You do not need medical detail.',
                    ],
                    'lt' => [
                        'Ši pamoka praktikuoja klausimus apie vaisto vartojimą.',
                        'Naudingos frazės:',
                        '- Kaip vartoti šį vaistą?',
                        '- Kiek kartų per dieną?',
                        '- Ryte ir vakare.',
                        '- Du kartus per dieną.',
                        'A1 tikslas: paklauskite klausimo. Medicininių detalių nereikia.',
                    ],
                ],
            ]),
            $this->scenario([
                'slug' => 'mokejimas-vaistineje',
                'title' => 'Mokėjimas vaistinėje',
                'title_en' => 'Paying at the Pharmacy',
                'subtitle' => 'Ask price and pay',
                'subtitle_lt' => 'Paklauskite kainos ir sumokėkite',
                'description' => 'Ask how much medicine costs and say how you will pay.',
                'description_lt' => 'Paklauskite, kiek kainuoja vaistas, ir pasakykite, kaip mokėsite.',
                'emoji' => '💳',
                'tone' => 'berry',
                'sort_order' => 115,
                'scene_slug' => 'mokejimas',
                'goal_slug' => 'pay-pharmacy',
                'goal_label' => 'Ask price or pay',
                'goal_label_lt' => 'Paklauskite kainos arba mokėkite',
                'goal_intent' => 'The learner asks the price or says they will pay by card or cash.',
                'goal_intent_lt' => 'Mokinys paklausia kainos arba pasako, kad mokės kortele ar grynaisiais.',
                'example' => 'Kiek kainuoja? Mokėsiu kortele.',
                'openings' => ['Kaip norėsite mokėti?', 'Ar mokėsite kortele ar grynaisiais?'],
                'replies' => ['Gerai. Mokėjimas priimtas.', 'Ačiū. Viskas gerai.'],
                'note' => [
                    'en' => [
                        'This lesson practises the final pharmacy step.',
                        'Useful phrases:',
                        '- Kiek kainuoja? = How much does it cost?',
                        '- Kiek tai kainuoja? = How much does this cost?',
                        '- Mokėsiu kortele. = I will pay by card.',
                        '- Mokėsiu grynaisiais. = I will pay in cash.',
                        'Focus: one price question plus one payment phrase is enough.',
                    ],
                    'lt' => [
                        'Ši pamoka praktikuoja paskutinį vaistinės žingsnį.',
                        'Naudingos frazės:',
                        '- Kiek kainuoja?',
                        '- Kiek tai kainuoja?',
                        '- Mokėsiu kortele.',
                        '- Mokėsiu grynaisiais.',
                        'Svarbu: užtenka vieno kainos klausimo ir vienos mokėjimo frazės.',
                    ],
                ],
            ]),
            [
                'slug' => 'vaistineje-pokalbis',
                'title' => 'Vaistinėje: pokalbis',
                'subtitle' => 'Handle a short pharmacy visit',
                'description' => 'Say the problem, ask for medicine, ask how to take it, and pay politely.',
                'emoji' => '🏥',
                'tone' => 'primary',
                'sort_order' => 116,
                'start_scene' => 'problema',
                'translations' => [
                    'title' => ['en' => 'At the Pharmacy: Conversation', 'lt' => 'Vaistinėje: pokalbis'],
                    'subtitle' => ['en' => 'Handle a short pharmacy visit', 'lt' => 'Atlikite trumpą pokalbį vaistinėje'],
                    'description' => ['en' => 'Say the problem, ask for medicine, ask how to take it, and pay politely.', 'lt' => 'Pasakykite problemą, paprašykite vaisto, paklauskite, kaip vartoti, ir mandagiai sumokėkite.'],
                ],
                'note' => [
                    'title' => 'Before: Pharmacy Conversation',
                    'body' => implode("\n\n", [
                        'This capstone combines the health and pharmacy phrases from the unit.',
                        'Useful flow:',
                        '- Man skauda galvą. = My head hurts.',
                        '- Prašau vaistų nuo galvos skausmo. = Medicine for headache, please.',
                        '- Kaip vartoti šį vaistą? = How should I take this medicine?',
                        '- Mokėsiu kortele. = I will pay by card.',
                        'Goal: complete a short, safe A1 pharmacy exchange.',
                    ]),
                    'translations' => [
                        'title' => ['en' => 'Before: Pharmacy Conversation', 'lt' => 'Prieš scenarijų: Pokalbis vaistinėje'],
                        'body' => ['en' => implode("\n\n", [
                            'This capstone combines the health and pharmacy phrases from the unit.',
                            'Useful flow:',
                            '- Man skauda galvą. = My head hurts.',
                            '- Prašau vaistų nuo galvos skausmo. = Medicine for headache, please.',
                            '- Kaip vartoti šį vaistą? = How should I take this medicine?',
                            '- Mokėsiu kortele. = I will pay by card.',
                            'Goal: complete a short, safe A1 pharmacy exchange.',
                        ]), 'lt' => implode("\n\n", [
                            'Šis pakartojimo scenarijus sujungia sveikatos ir vaistinės frazes iš šio skyriaus.',
                            'Naudinga seka:',
                            '- Man skauda galvą.',
                            '- Prašau vaistų nuo galvos skausmo.',
                            '- Kaip vartoti šį vaistą?',
                            '- Mokėsiu kortele.',
                            'Tikslas: atlikite trumpą ir saugų A1 pokalbį vaistinėje.',
                        ])],
                    ],
                ],
                'scenes' => [
                    $this->capstoneScene('problema', 'Problem', 'Problema', 'Gabija asks what is wrong.', 'Gabija klausia, kas atsitiko.', ['Kas jums yra?', 'Ką jums skauda?'], ['Suprantu. Galiu padėti.', 'Gerai. Pažiūrėkime vaistus.'], 'capstone-health-problem', 'Say the problem', 'Pasakykite problemą', 'The learner says one simple health problem.', 'Mokinys pasako vieną paprastą sveikatos problemą.', 'Man skauda galvą.', 'vaistas'),
                    $this->capstoneScene('vaistas', 'Medicine', 'Vaistas', 'Gabija asks what medicine the learner needs.', 'Gabija klausia, kokio vaisto mokiniui reikia.', ['Kokio vaisto reikia?', 'Ko ieškote?'], ['Žinoma. Štai vaistas.', 'Gerai. Turiu vaistų.'], 'capstone-ask-medicine', 'Ask for medicine', 'Paprašykite vaisto', 'The learner asks for medicine for a simple problem.', 'Mokinys paprašo vaisto nuo paprastos problemos.', 'Prašau vaistų nuo galvos skausmo.', 'vartojimas'),
                    $this->capstoneScene('vartojimas', 'Use and payment', 'Vartojimas ir mokėjimas', 'Gabija invites one usage or payment question.', 'Gabija laukia klausimo apie vartojimą arba mokėjimą.', ['Ką dar norite paklausti?', 'Ar norite paklausti kainos ar vartojimo?'], ['Gerai. Vartokite du kartus per dieną.', 'Ačiū. Mokėjimas priimtas.'], 'capstone-use-or-pay', 'Ask use or pay', 'Paklauskite vartojimo arba mokėkite', 'The learner asks how to take the medicine or says how they will pay.', 'Mokinys paklausia, kaip vartoti vaistą, arba pasako, kaip mokės.', 'Kaip vartoti šį vaistą?', null),
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
            'Kaip jaučiatės?' => 'How do you feel?',
            'Ar jums reikia pagalbos?' => 'Do you need help?',
            'Suprantu. Pabandysiu padėti.' => 'I understand. I will try to help.',
            'Gerai. Pasakykite daugiau.' => 'Good. Say more.',
            'Ką jums skauda?' => 'What hurts?',
            'Kas jums yra?' => 'What is the matter?',
            'Suprantu. Ačiū, kad pasakėte.' => 'I understand. Thank you for saying it.',
            'Gerai. Skausmas aiškus.' => 'Good. The pain is clear.',
            'Kokio vaisto reikia?' => 'What medicine do you need?',
            'Ko ieškote vaistinėje?' => 'What are you looking for at the pharmacy?',
            'Žinoma. Galiu pasiūlyti vaistų.' => 'Of course. I can suggest medicine.',
            'Gerai. Pažiūrėkime, kas tinka.' => 'Good. Let’s see what works.',
            'Ar norite paklausti, kaip vartoti?' => 'Do you want to ask how to take it?',
            'Ką dar norite žinoti?' => 'What else do you want to know?',
            'Vartokite du kartus per dieną.' => 'Take it twice a day.',
            'Gerkite ryte ir vakare.' => 'Take it in the morning and evening.',
            'Kaip norėsite mokėti?' => 'How would you like to pay?',
            'Ar mokėsite kortele ar grynaisiais?' => 'Will you pay by card or in cash?',
            'Gerai. Mokėjimas priimtas.' => 'Good. Payment accepted.',
            'Ačiū. Viskas gerai.' => 'Thank you. Everything is good.',
            'Ką dar norite paklausti?' => 'What else do you want to ask?',
            'Ar norite paklausti kainos ar vartojimo?' => 'Do you want to ask about price or use?',
            'Suprantu. Galiu padėti.' => 'I understand. I can help.',
            'Gerai. Pažiūrėkime vaistus.' => 'Good. Let’s look at medicine.',
            'Ko ieškote?' => 'What are you looking for?',
            'Žinoma. Štai vaistas.' => 'Of course. Here is the medicine.',
            'Gerai. Turiu vaistų.' => 'Good. I have medicine.',
            'Gerai. Vartokite du kartus per dieną.' => 'Good. Take it twice a day.',
            'Ačiū. Mokėjimas priimtas.' => 'Thank you. Payment accepted.',
            default => $line,
        };
    }
}
