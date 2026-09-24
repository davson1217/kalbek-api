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

class SkaiciaiLaikasDatosUnitSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $language = Language::query()->where('code', 'lt')->firstOrFail();
            $character = Character::query()->where('slug', 'gabija')->firstOrFail();
            $unit = Unit::query()->updateOrCreate(
                ['slug' => 'skaiciai-laikas-ir-datos'],
                [
                    'language_id' => $language->id,
                    'title' => 'Skaičiai, laikas ir datos',
                    'description' => 'A practical unit for numbers, age, phone numbers, time, simple days, and opening hours.',
                    'cefr_level' => 'a1',
                    'status' => ContentStatus::Published,
                    'sort_order' => 50,
                    'published_at' => now(),
                ],
            );

            $this->syncTranslations($unit, [
                'title' => ['en' => 'Numbers, Time and Dates', 'lt' => 'Skaičiai, laikas ir datos'],
                'description' => [
                    'en' => 'A practical unit for numbers, age, phone numbers, time, simple days, and opening hours.',
                    'lt' => 'Praktiškas skyrius apie skaičius, amžių, telefono numerius, laiką, paprastas dienas ir darbo laiką.',
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
                        'is_free' => $scenarioData['is_free'] ?? false,
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

            $sceneModel->goals()
                ->whereNotIn('slug', collect($scene['goals'])->pluck('slug')->all())
                ->delete();
        }

        $goals = Goal::query()
            ->whereIn('scene_id', $scenes->pluck('id'))
            ->get()
            ->keyBy('slug');

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
                    'support_translation' => [
                        'en' => $line['support_translation'],
                        'lt' => $line['target_text'],
                    ],
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
                'slug' => 'skaiciai-iki-desimt',
                'title' => 'Skaičiai iki dešimt',
                'title_en' => 'Numbers to Ten',
                'subtitle' => 'Say a simple number',
                'subtitle_lt' => 'Pasakykite paprastą skaičių',
                'description' => 'Understand and say numbers from one to ten.',
                'description_lt' => 'Supraskite ir pasakykite skaičius nuo vieno iki dešimt.',
                'emoji' => '🔢',
                'tone' => 'primary',
                'sort_order' => 41,
                'scene_slug' => 'skaicius',
                'goal_slug' => 'say-number',
                'goal_label' => 'Say a number',
                'goal_label_lt' => 'Pasakykite skaičių',
                'goal_intent' => 'The learner says one simple number from one to ten.',
                'goal_intent_lt' => 'Mokinys pasako vieną paprastą skaičių nuo vieno iki dešimt.',
                'example' => 'Penki.',
                'openings' => ['Pasakykite vieną skaičių nuo vieno iki dešimt.', 'Kokį skaičių pasirinksite?'],
                'replies' => ['Puiku. Skaičius aiškus.', 'Labai gerai. Girdžiu skaičių.'],
                'note' => [
                    'en' => [
                        'This lesson starts numbers for everyday speaking.',
                        'Useful words:',
                        '- Vienas. = One.',
                        '- Du. = Two.',
                        '- Trys. = Three.',
                        '- Keturi. = Four.',
                        '- Penki. = Five.',
                        '- Dešimt. = Ten.',
                        'Goal: say one number clearly. A short answer is enough.',
                    ],
                    'lt' => [
                        'Ši pamoka pradeda kasdienius skaičius.',
                        'Naudingi žodžiai:',
                        '- Vienas.',
                        '- Du.',
                        '- Trys.',
                        '- Keturi.',
                        '- Penki.',
                        '- Dešimt.',
                        'Tikslas: aiškiai pasakykite vieną skaičių. Trumpo atsakymo pakanka.',
                    ],
                ],
            ]),
            $this->scenario([
                'slug' => 'mano-amzius',
                'title' => 'Mano amžius',
                'title_en' => 'My Age',
                'subtitle' => 'Say how old you are',
                'subtitle_lt' => 'Pasakykite, kiek jums metų',
                'description' => 'Answer a simple question about age.',
                'description_lt' => 'Atsakykite į paprastą klausimą apie amžių.',
                'emoji' => '🎂',
                'tone' => 'mint',
                'sort_order' => 42,
                'scene_slug' => 'amzius',
                'goal_slug' => 'say-age',
                'goal_label' => 'Say your age',
                'goal_label_lt' => 'Pasakykite savo amžių',
                'goal_intent' => 'The learner says how old they are using a simple age phrase.',
                'goal_intent_lt' => 'Mokinys paprasta fraze pasako, kiek jam arba jai metų.',
                'example' => 'Man dvidešimt metų.',
                'openings' => ['Kiek jums metų?', 'Pasakykite, kiek jums metų.'],
                'replies' => ['Ačiū. Dabar žinau jūsų amžių.', 'Gerai. Atsakymas aiškus.'],
                'note' => [
                    'en' => [
                        'This lesson helps you answer about age.',
                        'Useful phrases:',
                        '- Kiek jums metų? = How old are you?',
                        '- Man ... metų. = I am ... years old.',
                        '- Man dvidešimt metų. = I am twenty years old.',
                        '- Man trisdešimt metų. = I am thirty years old.',
                        'Tiny grammar: Lithuanian says “to me ... years.” Learn Man ... metų as one speaking pattern.',
                    ],
                    'lt' => [
                        'Ši pamoka padeda atsakyti apie amžių.',
                        'Naudingos frazės:',
                        '- Kiek jums metų?',
                        '- Man ... metų.',
                        '- Man dvidešimt metų.',
                        '- Man trisdešimt metų.',
                        'Maža gramatikos pastaba: mokykitės Man ... metų kaip vieną kalbėjimo modelį.',
                    ],
                ],
            ]),
            $this->scenario([
                'slug' => 'telefono-numeris',
                'title' => 'Telefono numeris',
                'title_en' => 'Phone Number',
                'subtitle' => 'Say a phone number slowly',
                'subtitle_lt' => 'Lėtai pasakykite telefono numerį',
                'description' => 'Practise giving a short phone number with clear digits.',
                'description_lt' => 'Praktikuokite trumpą telefono numerį aiškiais skaitmenimis.',
                'emoji' => '📱',
                'tone' => 'sky',
                'sort_order' => 43,
                'scene_slug' => 'telefonas',
                'goal_slug' => 'say-phone-number',
                'goal_label' => 'Say a phone number',
                'goal_label_lt' => 'Pasakykite telefono numerį',
                'goal_intent' => 'The learner says a phone number or short number sequence clearly.',
                'goal_intent_lt' => 'Mokinys aiškiai pasako telefono numerį arba trumpą skaičių seką.',
                'example' => 'Mano numeris yra aštuoni, šeši, vienas, du.',
                'openings' => ['Koks jūsų telefono numeris?', 'Pasakykite telefono numerį lėtai.'],
                'replies' => ['Ačiū. Numerį užrašiau.', 'Gerai. Pakankamai aišku.'],
                'note' => [
                    'en' => [
                        'This lesson practises saying digits slowly.',
                        'Useful phrases:',
                        '- Koks jūsų telefono numeris? = What is your phone number?',
                        '- Mano numeris yra ... = My number is ...',
                        '- Prašau pakartoti. = Please repeat.',
                        '- Lėtai. = Slowly.',
                        'Tip: say digits one by one. You do not need a real number.',
                    ],
                    'lt' => [
                        'Ši pamoka praktikuoja skaitmenų sakymą lėtai.',
                        'Naudingos frazės:',
                        '- Koks jūsų telefono numeris?',
                        '- Mano numeris yra ...',
                        '- Prašau pakartoti.',
                        '- Lėtai.',
                        'Patarimas: sakykite skaitmenis po vieną. Tikro numerio nereikia.',
                    ],
                ],
            ]),
            $this->scenario([
                'slug' => 'kiek-valandu',
                'title' => 'Kiek valandų?',
                'title_en' => 'What Time Is It?',
                'subtitle' => 'Ask or answer the time',
                'subtitle_lt' => 'Paklauskite arba atsakykite laiką',
                'description' => 'Use a very simple time question or answer.',
                'description_lt' => 'Vartokite labai paprastą klausimą arba atsakymą apie laiką.',
                'emoji' => '🕒',
                'tone' => 'amber',
                'sort_order' => 44,
                'scene_slug' => 'laikas',
                'goal_slug' => 'ask-or-say-time',
                'goal_label' => 'Ask or say the time',
                'goal_label_lt' => 'Paklauskite arba pasakykite laiką',
                'goal_intent' => 'The learner asks what time it is or gives a simple hour.',
                'goal_intent_lt' => 'Mokinys paklausia, kiek valandų, arba pasako paprastą valandą.',
                'example' => 'Kiek valandų?',
                'openings' => ['Galite paklausti laiko.', 'Kiek dabar valandų?'],
                'replies' => ['Dabar trečia valanda.', 'Gerai. Laiką pasakėte aiškiai.'],
                'note' => [
                    'en' => [
                        'This lesson gives you simple time phrases.',
                        'Useful phrases:',
                        '- Kiek valandų? = What time is it?',
                        '- Dabar pirma valanda. = It is one o’clock now.',
                        '- Dabar antra valanda. = It is two o’clock now.',
                        '- Dabar trečia valanda. = It is three o’clock now.',
                        'Focus: at A1, simple full hours are enough.',
                    ],
                    'lt' => [
                        'Ši pamoka duoda paprastas laiko frazes.',
                        'Naudingos frazės:',
                        '- Kiek valandų?',
                        '- Dabar pirma valanda.',
                        '- Dabar antra valanda.',
                        '- Dabar trečia valanda.',
                        'Svarbu: A1 lygyje pakanka paprastų pilnų valandų.',
                    ],
                ],
            ]),
            $this->scenario([
                'slug' => 'siandien-ar-rytoj',
                'title' => 'Šiandien ar rytoj?',
                'title_en' => 'Today or Tomorrow?',
                'subtitle' => 'Say a simple day',
                'subtitle_lt' => 'Pasakykite paprastą dieną',
                'description' => 'Use today, tomorrow, yesterday, or one weekday in a short answer.',
                'description_lt' => 'Trumpame atsakyme vartokite šiandien, rytoj, vakar arba vieną savaitės dieną.',
                'emoji' => '📅',
                'tone' => 'berry',
                'sort_order' => 45,
                'scene_slug' => 'diena',
                'goal_slug' => 'say-day',
                'goal_label' => 'Say when',
                'goal_label_lt' => 'Pasakykite kada',
                'goal_intent' => 'The learner says when something happens using today, tomorrow, yesterday, or a weekday.',
                'goal_intent_lt' => 'Mokinys pasako, kada kas nors vyksta, vartodamas šiandien, rytoj, vakar arba savaitės dieną.',
                'example' => 'Rytoj.',
                'openings' => ['Kada susitinkame: šiandien ar rytoj?', 'Kada jums tinka?'],
                'replies' => ['Gerai, tinka.', 'Puiku. Dieną supratau.'],
                'note' => [
                    'en' => [
                        'This lesson practises simple day words.',
                        'Useful words:',
                        '- Šiandien. = Today.',
                        '- Rytoj. = Tomorrow.',
                        '- Vakar. = Yesterday.',
                        '- Pirmadienį. = On Monday.',
                        '- Penktadienį. = On Friday.',
                        'Tip: one word can be a good answer when the question is clear.',
                    ],
                    'lt' => [
                        'Ši pamoka praktikuoja paprastus dienų žodžius.',
                        'Naudingi žodžiai:',
                        '- Šiandien.',
                        '- Rytoj.',
                        '- Vakar.',
                        '- Pirmadienį.',
                        '- Penktadienį.',
                        'Patarimas: vienas žodis gali būti geras atsakymas, kai klausimas aiškus.',
                    ],
                ],
            ]),
            [
                'slug' => 'susitikimo-laikas',
                'title' => 'Susitikimo laikas',
                'subtitle' => 'Put days and time together',
                'description' => 'Choose a day, say a time, and confirm a simple meeting.',
                'emoji' => '🗓️',
                'tone' => 'primary',
                'sort_order' => 46,
                'start_scene' => 'dienos-pasirinkimas',
                'translations' => [
                    'title' => ['en' => 'Meeting Time', 'lt' => 'Susitikimo laikas'],
                    'subtitle' => ['en' => 'Put days and time together', 'lt' => 'Sujunkite dienas ir laiką'],
                    'description' => ['en' => 'Choose a day, say a time, and confirm a simple meeting.', 'lt' => 'Pasirinkite dieną, pasakykite laiką ir patvirtinkite paprastą susitikimą.'],
                ],
                'note' => [
                    'title' => 'Before: Meeting Time',
                    'body' => implode("\n\n", [
                        'This capstone combines the numbers, days, and time phrases from the unit.',
                        'Useful flow:',
                        '- Kada jums tinka? = When works for you?',
                        '- Rytoj. = Tomorrow.',
                        '- Kelintą valandą? = At what time?',
                        '- Trečią valandą. = At three o’clock.',
                        '- Puiku, ačiū. = Great, thank you.',
                        'Goal: answer in short, clear steps. You do not need a long sentence.',
                    ]),
                    'translations' => [
                        'title' => ['en' => 'Before: Meeting Time', 'lt' => 'Prieš scenarijų: Susitikimo laikas'],
                        'body' => ['en' => implode("\n\n", [
                            'This capstone combines the numbers, days, and time phrases from the unit.',
                            'Useful flow:',
                            '- Kada jums tinka? = When works for you?',
                            '- Rytoj. = Tomorrow.',
                            '- Kelintą valandą? = At what time?',
                            '- Trečią valandą. = At three o’clock.',
                            '- Puiku, ačiū. = Great, thank you.',
                            'Goal: answer in short, clear steps. You do not need a long sentence.',
                        ]), 'lt' => implode("\n\n", [
                            'Šis pakartojimo scenarijus sujungia skaičius, dienas ir laiko frazes iš šio skyriaus.',
                            'Naudinga seka:',
                            '- Kada jums tinka?',
                            '- Rytoj.',
                            '- Kelintą valandą?',
                            '- Trečią valandą.',
                            '- Puiku, ačiū.',
                            'Tikslas: atsakykite trumpais, aiškiais žingsniais. Ilgo sakinio nereikia.',
                        ])],
                    ],
                ],
                'scenes' => [
                    [
                        'slug' => 'dienos-pasirinkimas',
                        'title' => 'Choose a day',
                        'setting' => 'Gabija asks which day works for a short meeting.',
                        'translations' => [
                            'title' => ['en' => 'Choose a day', 'lt' => 'Pasirinkite dieną'],
                            'setting' => ['en' => 'Gabija asks which day works for a short meeting.', 'lt' => 'Gabija klausia, kuri diena tinka trumpam susitikimui.'],
                        ],
                        'lines' => [
                            ['target_text' => 'Kada jums tinka: šiandien ar rytoj?', 'support_translation' => 'When works for you: today or tomorrow?', 'trigger_goal' => null],
                            ['target_text' => 'Kuri diena jums tinka?', 'support_translation' => 'Which day works for you?', 'trigger_goal' => null],
                            ['target_text' => 'Gerai. Diena tinka.', 'support_translation' => 'Good. The day works.', 'trigger_goal' => 'choose-meeting-day', 'priority' => 100],
                            ['target_text' => 'Puiku. Dieną užrašiau.', 'support_translation' => 'Great. I wrote down the day.', 'trigger_goal' => 'choose-meeting-day', 'priority' => 100],
                        ],
                        'goals' => [
                            [
                                'slug' => 'choose-meeting-day',
                                'label' => 'Choose a day',
                                'intent' => 'The learner chooses a simple day for a meeting, such as today, tomorrow, or a weekday.',
                                'example' => 'Rytoj.',
                                'next' => 'valandos-pasirinkimas',
                                'translations' => [
                                    'label' => ['en' => 'Choose a day', 'lt' => 'Pasirinkite dieną'],
                                    'intent' => ['en' => 'The learner chooses a simple day for a meeting, such as today, tomorrow, or a weekday.', 'lt' => 'Mokinys pasirenka paprastą susitikimo dieną, pavyzdžiui, šiandien, rytoj arba savaitės dieną.'],
                                ],
                            ],
                        ],
                    ],
                    [
                        'slug' => 'valandos-pasirinkimas',
                        'title' => 'Choose a time',
                        'setting' => 'Gabija asks what time works.',
                        'translations' => [
                            'title' => ['en' => 'Choose a time', 'lt' => 'Pasirinkite laiką'],
                            'setting' => ['en' => 'Gabija asks what time works.', 'lt' => 'Gabija klausia, kelinta valanda tinka.'],
                        ],
                        'lines' => [
                            ['target_text' => 'Kelintą valandą jums tinka?', 'support_translation' => 'At what time works for you?', 'trigger_goal' => null],
                            ['target_text' => 'Kada tiksliai: antrą ar trečią valandą?', 'support_translation' => 'When exactly: at two or at three o’clock?', 'trigger_goal' => null],
                            ['target_text' => 'Gerai. Laiką užrašiau.', 'support_translation' => 'Good. I wrote down the time.', 'trigger_goal' => 'choose-meeting-time', 'priority' => 100],
                            ['target_text' => 'Puiku. Laikas aiškus.', 'support_translation' => 'Great. The time is clear.', 'trigger_goal' => 'choose-meeting-time', 'priority' => 100],
                        ],
                        'goals' => [
                            [
                                'slug' => 'choose-meeting-time',
                                'label' => 'Choose a time',
                                'intent' => 'The learner says a simple meeting time using an hour.',
                                'example' => 'Trečią valandą.',
                                'next' => 'patvirtinimas',
                                'translations' => [
                                    'label' => ['en' => 'Choose a time', 'lt' => 'Pasirinkite laiką'],
                                    'intent' => ['en' => 'The learner says a simple meeting time using an hour.', 'lt' => 'Mokinys pasako paprastą susitikimo laiką su valanda.'],
                                ],
                            ],
                        ],
                    ],
                    [
                        'slug' => 'patvirtinimas',
                        'title' => 'Confirm the meeting',
                        'setting' => 'Gabija repeats the meeting time and waits for confirmation.',
                        'translations' => [
                            'title' => ['en' => 'Confirm the meeting', 'lt' => 'Patvirtinkite susitikimą'],
                            'setting' => ['en' => 'Gabija repeats the meeting time and waits for confirmation.', 'lt' => 'Gabija pakartoja susitikimo laiką ir laukia patvirtinimo.'],
                        ],
                        'lines' => [
                            ['target_text' => 'Gerai, susitinkame rytoj trečią valandą.', 'support_translation' => 'Good, we are meeting tomorrow at three o’clock.', 'trigger_goal' => null],
                            ['target_text' => 'Ar tinka?', 'support_translation' => 'Does that work?', 'trigger_goal' => null],
                            ['target_text' => 'Puiku. Iki susitikimo!', 'support_translation' => 'Great. See you at the meeting!', 'trigger_goal' => 'confirm-meeting', 'priority' => 100],
                            ['target_text' => 'Labai gerai. Ačiū ir iki!', 'support_translation' => 'Very good. Thank you and bye!', 'trigger_goal' => 'confirm-meeting', 'priority' => 100],
                        ],
                        'goals' => [
                            [
                                'slug' => 'confirm-meeting',
                                'label' => 'Confirm politely',
                                'intent' => 'The learner confirms the meeting politely or thanks Gabija.',
                                'example' => 'Tinka, ačiū.',
                                'next' => null,
                                'translations' => [
                                    'label' => ['en' => 'Confirm politely', 'lt' => 'Mandagiai patvirtinkite'],
                                    'intent' => ['en' => 'The learner confirms the meeting politely or thanks Gabija.', 'lt' => 'Mokinys mandagiai patvirtina susitikimą arba padėkoja Gabijai.'],
                                ],
                            ],
                        ],
                    ],
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
            'Pasakykite vieną skaičių nuo vieno iki dešimt.' => 'Say one number from one to ten.',
            'Kokį skaičių pasirinksite?' => 'What number will you choose?',
            'Puiku. Skaičius aiškus.' => 'Great. The number is clear.',
            'Labai gerai. Girdžiu skaičių.' => 'Very good. I hear the number.',
            'Kiek jums metų?' => 'How old are you?',
            'Pasakykite, kiek jums metų.' => 'Say how old you are.',
            'Ačiū. Dabar žinau jūsų amžių.' => 'Thank you. Now I know your age.',
            'Gerai. Atsakymas aiškus.' => 'Good. The answer is clear.',
            'Koks jūsų telefono numeris?' => 'What is your phone number?',
            'Pasakykite telefono numerį lėtai.' => 'Say the phone number slowly.',
            'Ačiū. Numerį užrašiau.' => 'Thank you. I wrote down the number.',
            'Gerai. Pakankamai aišku.' => 'Good. Clear enough.',
            'Galite paklausti laiko.' => 'You can ask the time.',
            'Kiek dabar valandų?' => 'What time is it now?',
            'Dabar trečia valanda.' => 'It is three o’clock now.',
            'Gerai. Laiką pasakėte aiškiai.' => 'Good. You said the time clearly.',
            'Kada susitinkame: šiandien ar rytoj?' => 'When are we meeting: today or tomorrow?',
            'Kada jums tinka?' => 'When works for you?',
            'Gerai, tinka.' => 'Good, that works.',
            'Puiku. Dieną supratau.' => 'Great. I understood the day.',
            'Laba diena. Galiu padėti?' => 'Good day. Can I help?',
            'Sveiki. Ko norėtumėte paklausti?' => 'Hello. What would you like to ask?',
            'Dirbame nuo devintos iki penktos.' => 'We are open from nine to five.',
            'Šiandien dirbame iki penktos valandos.' => 'Today we are open until five o’clock.',
            default => $line,
        };
    }
}
