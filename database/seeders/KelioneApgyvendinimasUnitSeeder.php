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

class KelioneApgyvendinimasUnitSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $language = Language::query()->where('code', 'lt')->firstOrFail();
            $character = Character::query()->where('slug', 'gabija')->firstOrFail();
            $unit = Unit::query()->updateOrCreate(
                ['slug' => 'kelione-ir-apgyvendinimas'],
                [
                    'language_id' => $language->id,
                    'title' => 'Kelionė ir apgyvendinimas',
                    'description' => 'A practical unit for hotel check-in, reservations, room numbers, keys, and simple room problems.',
                    'cefr_level' => 'a1',
                    'status' => ContentStatus::Published,
                    'sort_order' => 160,
                    'published_at' => now(),
                ],
            );

            $this->syncTranslations($unit, [
                'title' => ['en' => 'Travel and Accommodation', 'lt' => 'Kelionė ir apgyvendinimas'],
                'description' => [
                    'en' => 'A practical unit for hotel check-in, reservations, room numbers, keys, and simple room problems.',
                    'lt' => 'Praktiškas skyrius apie registraciją viešbutyje, rezervacijas, kambario numerius, raktus ir paprastas kambario problemas.',
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
                'slug' => 'viesbutyje-registracija',
                'title' => 'Viešbutyje',
                'title_en' => 'At the Hotel',
                'subtitle' => 'Say you have a reservation',
                'subtitle_lt' => 'Pasakykite, kad turite rezervaciją',
                'description' => 'Greet reception and say that you have a reservation.',
                'description_lt' => 'Pasisveikinkite registratūroje ir pasakykite, kad turite rezervaciją.',
                'emoji' => '🏨',
                'tone' => 'primary',
                'sort_order' => 161,
                'scene_slug' => 'registracija',
                'goal_slug' => 'say-reservation',
                'goal_label' => 'Say you have a reservation',
                'goal_label_lt' => 'Pasakykite, kad turite rezervaciją',
                'goal_intent' => 'The learner greets reception and says they have a reservation.',
                'goal_intent_lt' => 'Mokinys pasisveikina registratūroje ir pasako, kad turi rezervaciją.',
                'example' => 'Laba diena. Turiu rezervaciją.',
                'openings' => ['Laba diena. Kuo galiu padėti?', 'Sveiki atvykę. Ar turite rezervaciją?'],
                'replies' => ['Gerai. Patikrinsiu rezervaciją.', 'Puiku. Prašau pasakyti vardą.'],
                'note' => [
                    'en' => [
                        'This lesson starts a hotel check-in.',
                        'Useful phrases:',
                        '- Laba diena. = Good day.',
                        '- Turiu rezervaciją. = I have a reservation.',
                        '- Neturiu rezervacijos. = I do not have a reservation.',
                        '- Kuo galiu padėti? = How can I help?',
                        'Focus: turiu means I have.',
                    ],
                    'lt' => [
                        'Ši pamoka pradeda registraciją viešbutyje.',
                        'Naudingos frazės:',
                        '- Laba diena.',
                        '- Turiu rezervaciją.',
                        '- Neturiu rezervacijos.',
                        '- Kuo galiu padėti?',
                        'Svarbu: turiu reiškia I have.',
                    ],
                ],
            ]),
            $this->scenario([
                'slug' => 'vardas-registracijai',
                'title' => 'Mano vardas',
                'title_en' => 'My Name',
                'subtitle' => 'Give your name for check-in',
                'subtitle_lt' => 'Pasakykite vardą registracijai',
                'description' => 'Give your name when reception asks for it.',
                'description_lt' => 'Pasakykite savo vardą, kai registratūra jo paprašo.',
                'emoji' => '🪪',
                'tone' => 'mint',
                'sort_order' => 162,
                'scene_slug' => 'vardas',
                'goal_slug' => 'give-checkin-name',
                'goal_label' => 'Give your name',
                'goal_label_lt' => 'Pasakykite savo vardą',
                'goal_intent' => 'The learner gives their name for hotel check-in.',
                'goal_intent_lt' => 'Mokinys pasako savo vardą registracijai viešbutyje.',
                'example' => 'Mano vardas Tomas.',
                'openings' => ['Koks jūsų vardas?', 'Prašau pasakyti vardą.'],
                'replies' => ['Ačiū. Patikrinsiu.', 'Gerai. Radau rezervaciją.'],
                'note' => [
                    'en' => [
                        'This lesson reuses your name in a hotel context.',
                        'Useful phrases:',
                        '- Koks jūsų vardas? = What is your name?',
                        '- Mano vardas Tomas. = My name is Tomas.',
                        '- Pavardė yra Petrauskas. = The surname is Petrauskas.',
                        '- Prašau pasakyti vardą. = Please say your name.',
                        'A1 goal: give a name clearly.',
                    ],
                    'lt' => [
                        'Ši pamoka panaudoja vardą viešbučio kontekste.',
                        'Naudingos frazės:',
                        '- Koks jūsų vardas?',
                        '- Mano vardas Tomas.',
                        '- Pavardė yra Petrauskas.',
                        '- Prašau pasakyti vardą.',
                        'A1 tikslas: aiškiai pasakykite vardą.',
                    ],
                ],
            ]),
            $this->scenario([
                'slug' => 'kambarys',
                'title' => 'Kambarys',
                'title_en' => 'Room',
                'subtitle' => 'Ask for or confirm a room',
                'subtitle_lt' => 'Paprašykite arba patvirtinkite kambarį',
                'description' => 'Ask for a room or confirm that you need one room.',
                'description_lt' => 'Paprašykite kambario arba patvirtinkite, kad reikia vieno kambario.',
                'emoji' => '🛏️',
                'tone' => 'sky',
                'sort_order' => 163,
                'scene_slug' => 'kambario-prasymas',
                'goal_slug' => 'ask-room',
                'goal_label' => 'Ask for a room',
                'goal_label_lt' => 'Paprašykite kambario',
                'goal_intent' => 'The learner asks for or confirms a simple hotel room need.',
                'goal_intent_lt' => 'Mokinys paprašo kambario arba patvirtina paprastą kambario poreikį.',
                'example' => 'Man reikia vieno kambario.',
                'openings' => ['Kokio kambario reikia?', 'Ar reikia vieno kambario?'],
                'replies' => ['Gerai. Kambarys yra paruoštas.', 'Puiku. Kambario poreikis aiškus.'],
                'note' => [
                    'en' => [
                        'This lesson practises a simple room request.',
                        'Useful phrases:',
                        '- Man reikia kambario. = I need a room.',
                        '- Man reikia vieno kambario. = I need one room.',
                        '- Ar kambarys paruoštas? = Is the room ready?',
                        '- Taip, vieno kambario. = Yes, one room.',
                        'Focus: kambarys means room.',
                    ],
                    'lt' => [
                        'Ši pamoka praktikuoja paprastą kambario prašymą.',
                        'Naudingos frazės:',
                        '- Man reikia kambario.',
                        '- Man reikia vieno kambario.',
                        '- Ar kambarys paruoštas?',
                        '- Taip, vieno kambario.',
                        'Svarbu: kambarys reiškia room.',
                    ],
                ],
            ]),
            $this->scenario([
                'slug' => 'raktas-ir-numeris',
                'title' => 'Raktas ir numeris',
                'title_en' => 'Key and Number',
                'subtitle' => 'Ask for the key or room number',
                'subtitle_lt' => 'Paklauskite rakto arba kambario numerio',
                'description' => 'Ask for the key or understand a simple room number.',
                'description_lt' => 'Paklauskite rakto arba supraskite paprastą kambario numerį.',
                'emoji' => '🔑',
                'tone' => 'amber',
                'sort_order' => 164,
                'scene_slug' => 'raktas',
                'goal_slug' => 'ask-key-number',
                'goal_label' => 'Ask for key or number',
                'goal_label_lt' => 'Paklauskite rakto arba numerio',
                'goal_intent' => 'The learner asks for the key or room number.',
                'goal_intent_lt' => 'Mokinys paklausia rakto arba kambario numerio.',
                'example' => 'Koks mano kambario numeris?',
                'openings' => ['Štai jūsų kambarys.', 'Ko dar reikia registracijai?'],
                'replies' => ['Gerai. Štai raktas.', 'Kambario numerį pasakysiu dabar.'],
                'note' => [
                    'en' => [
                        'This lesson connects hotel words with numbers.',
                        'Useful phrases:',
                        '- Prašau rakto. = The key, please.',
                        '- Koks kambario numeris? = What is the room number?',
                        '- Mano kambario numeris? = My room number?',
                        '- Ačiū už raktą. = Thank you for the key.',
                        'Focus: raktas means key; numeris means number.',
                    ],
                    'lt' => [
                        'Ši pamoka sujungia viešbučio žodžius su skaičiais.',
                        'Naudingos frazės:',
                        '- Prašau rakto.',
                        '- Koks kambario numeris?',
                        '- Mano kambario numeris?',
                        '- Ačiū už raktą.',
                        'Svarbu: raktas reiškia key, numeris reiškia number.',
                    ],
                ],
            ]),
            $this->scenario([
                'slug' => 'problema-kambaryje',
                'title' => 'Problema kambaryje',
                'title_en' => 'Problem in the Room',
                'subtitle' => 'Say a simple room problem',
                'subtitle_lt' => 'Pasakykite paprastą kambario problemą',
                'description' => 'Say that there is no key, towel, water, or that the room is cold.',
                'description_lt' => 'Pasakykite, kad nėra rakto, rankšluosčio, vandens arba kad kambaryje šalta.',
                'emoji' => '🧳',
                'tone' => 'berry',
                'sort_order' => 165,
                'scene_slug' => 'problema',
                'goal_slug' => 'say-room-problem',
                'goal_label' => 'Say a room problem',
                'goal_label_lt' => 'Pasakykite kambario problemą',
                'goal_intent' => 'The learner says one simple problem with the hotel room.',
                'goal_intent_lt' => 'Mokinys pasako vieną paprastą problemą su viešbučio kambariu.',
                'example' => 'Kambaryje nėra rankšluosčio.',
                'openings' => ['Ar yra problema?', 'Kas negerai kambaryje?'],
                'replies' => ['Suprantu. Pabandysiu padėti.', 'Gerai. Užregistruosiu problemą.'],
                'note' => [
                    'en' => [
                        'This lesson gives simple hotel problem phrases.',
                        'Useful phrases:',
                        '- Kambaryje nėra rakto. = There is no key in the room.',
                        '- Kambaryje nėra rankšluosčio. = There is no towel in the room.',
                        '- Kambaryje šalta. = It is cold in the room.',
                        '- Reikia pagalbos. = Help is needed.',
                        'Focus: nėra means there is no.',
                    ],
                    'lt' => [
                        'Ši pamoka duoda paprastas viešbučio problemų frazes.',
                        'Naudingos frazės:',
                        '- Kambaryje nėra rakto.',
                        '- Kambaryje nėra rankšluosčio.',
                        '- Kambaryje šalta.',
                        '- Reikia pagalbos.',
                        'Svarbu: nėra reiškia there is no.',
                    ],
                ],
            ]),
            [
                'slug' => 'atvykimas-i-viesbuti',
                'title' => 'Atvykimas į viešbutį',
                'subtitle' => 'Check in and ask for help',
                'description' => 'Check in, give your name, confirm the room, ask for the key, and report a simple problem.',
                'emoji' => '🛎️',
                'tone' => 'primary',
                'sort_order' => 166,
                'start_scene' => 'registracija',
                'translations' => [
                    'title' => ['en' => 'Hotel Arrival', 'lt' => 'Atvykimas į viešbutį'],
                    'subtitle' => ['en' => 'Check in and ask for help', 'lt' => 'Užsiregistruokite ir paprašykite pagalbos'],
                    'description' => ['en' => 'Check in, give your name, confirm the room, ask for the key, and report a simple problem.', 'lt' => 'Užsiregistruokite, pasakykite vardą, patvirtinkite kambarį, paprašykite rakto ir praneškite paprastą problemą.'],
                ],
                'note' => [
                    'title' => 'Before: Hotel Arrival',
                    'body' => implode("\n\n", [
                        'This capstone combines hotel arrival phrases.',
                        'Useful flow:',
                        '- Turiu rezervaciją. = I have a reservation.',
                        '- Mano vardas Tomas. = My name is Tomas.',
                        '- Man reikia vieno kambario. = I need one room.',
                        '- Koks kambario numeris? = What is the room number?',
                        '- Kambaryje nėra rankšluosčio. = There is no towel in the room.',
                        'Goal: handle a short A1 hotel check-in.',
                    ]),
                    'translations' => [
                        'title' => ['en' => 'Before: Hotel Arrival', 'lt' => 'Prieš scenarijų: Atvykimas į viešbutį'],
                        'body' => ['en' => implode("\n\n", [
                            'This capstone combines hotel arrival phrases.',
                            'Useful flow:',
                            '- Turiu rezervaciją. = I have a reservation.',
                            '- Mano vardas Tomas. = My name is Tomas.',
                            '- Man reikia vieno kambario. = I need one room.',
                            '- Koks kambario numeris? = What is the room number?',
                            '- Kambaryje nėra rankšluosčio. = There is no towel in the room.',
                            'Goal: handle a short A1 hotel check-in.',
                        ]), 'lt' => implode("\n\n", [
                            'Šis pakartojimo scenarijus sujungia atvykimo į viešbutį frazes.',
                            'Naudinga seka:',
                            '- Turiu rezervaciją.',
                            '- Mano vardas Tomas.',
                            '- Man reikia vieno kambario.',
                            '- Koks kambario numeris?',
                            '- Kambaryje nėra rankšluosčio.',
                            'Tikslas: atlikite trumpą A1 registraciją viešbutyje.',
                        ])],
                    ],
                ],
                'scenes' => [
                    $this->capstoneScene('registracija', 'Check-in', 'Registracija', 'Gabija welcomes the learner at reception.', 'Gabija pasitinka mokinį registratūroje.', ['Laba diena. Kuo galiu padėti?', 'Sveiki atvykę. Ar turite rezervaciją?'], ['Gerai. Patikrinsiu rezervaciją.', 'Puiku. Tęskime registraciją.'], 'capstone-reservation', 'Say you have a reservation', 'Pasakykite, kad turite rezervaciją', 'The learner says they have a reservation.', 'Mokinys pasako, kad turi rezervaciją.', 'Turiu rezervaciją.', 'vardas'),
                    $this->capstoneScene('vardas', 'Name', 'Vardas', 'Gabija asks for the learner’s name.', 'Gabija klausia mokinio vardo.', ['Koks jūsų vardas?', 'Prašau pasakyti vardą.'], ['Ačiū. Patikrinsiu.', 'Gerai. Radau informaciją.'], 'capstone-checkin-name', 'Give your name', 'Pasakykite vardą', 'The learner gives their name for check-in.', 'Mokinys pasako savo vardą registracijai.', 'Mano vardas Tomas.', 'kambarys'),
                    $this->capstoneScene('kambarys', 'Room', 'Kambarys', 'Gabija asks what room is needed.', 'Gabija klausia, kokio kambario reikia.', ['Kokio kambario reikia?', 'Ar reikia vieno kambario?'], ['Gerai. Kambarys paruoštas.', 'Puiku. Kambario poreikis aiškus.'], 'capstone-room', 'Confirm room need', 'Patvirtinkite kambario poreikį', 'The learner asks for or confirms a simple room need.', 'Mokinys paprašo kambario arba patvirtina paprastą kambario poreikį.', 'Man reikia vieno kambario.', 'raktas'),
                    $this->capstoneScene('raktas', 'Key', 'Raktas', 'Gabija gives the room and waits for a key or number question.', 'Gabija duoda kambarį ir laukia klausimo apie raktą arba numerį.', ['Ko dar reikia registracijai?', 'Ar reikia rakto?'], ['Gerai. Štai raktas.', 'Kambario numerį pasakysiu dabar.'], 'capstone-key-number', 'Ask for key or number', 'Paklauskite rakto arba numerio', 'The learner asks for the key or room number.', 'Mokinys paklausia rakto arba kambario numerio.', 'Koks mano kambario numeris?', null),
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
            'Laba diena. Kuo galiu padėti?' => 'Good day. How can I help?',
            'Sveiki atvykę. Ar turite rezervaciją?' => 'Welcome. Do you have a reservation?',
            'Gerai. Patikrinsiu rezervaciją.' => 'Good. I will check the reservation.',
            'Puiku. Prašau pasakyti vardą.' => 'Great. Please say your name.',
            'Koks jūsų vardas?' => 'What is your name?',
            'Prašau pasakyti vardą.' => 'Please say your name.',
            'Ačiū. Patikrinsiu.' => 'Thank you. I will check.',
            'Gerai. Radau rezervaciją.' => 'Good. I found the reservation.',
            'Kokio kambario reikia?' => 'What room is needed?',
            'Ar reikia vieno kambario?' => 'Is one room needed?',
            'Gerai. Kambarys yra paruoštas.' => 'Good. The room is ready.',
            'Puiku. Kambario poreikis aiškus.' => 'Great. The room need is clear.',
            'Štai jūsų kambarys.' => 'Here is your room.',
            'Ko dar reikia registracijai?' => 'What else is needed for check-in?',
            'Gerai. Štai raktas.' => 'Good. Here is the key.',
            'Kambario numerį pasakysiu dabar.' => 'I will say the room number now.',
            'Ar yra problema?' => 'Is there a problem?',
            'Kas negerai kambaryje?' => 'What is wrong in the room?',
            'Suprantu. Pabandysiu padėti.' => 'I understand. I will try to help.',
            'Gerai. Užregistruosiu problemą.' => 'Good. I will register the problem.',
            'Puiku. Tęskime registraciją.' => 'Great. Let’s continue check-in.',
            'Gerai. Radau informaciją.' => 'Good. I found the information.',
            'Gerai. Kambarys paruoštas.' => 'Good. The room is ready.',
            'Ar reikia rakto?' => 'Is the key needed?',
            default => $line,
        };
    }
}
