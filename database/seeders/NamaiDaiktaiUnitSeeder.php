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

class NamaiDaiktaiUnitSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $language = Language::query()->where('code', 'lt')->firstOrFail();
            $character = Character::query()->where('slug', 'gabija')->firstOrFail();
            $unit = Unit::query()->updateOrCreate(
                ['slug' => 'namai-ir-daiktai'],
                [
                    'language_id' => $language->id,
                    'title' => 'Namai ir daiktai',
                    'description' => 'A practical unit for rooms, common home objects, simple location words, possession, and asking for things.',
                    'cefr_level' => 'a1',
                    'status' => ContentStatus::Published,
                    'sort_order' => 80,
                    'published_at' => now(),
                ],
            );

            $this->syncTranslations($unit, [
                'title' => ['en' => 'Home and Objects', 'lt' => 'Namai ir daiktai'],
                'description' => [
                    'en' => 'A practical unit for rooms, common home objects, simple location words, possession, and asking for things.',
                    'lt' => 'Praktiškas skyrius apie kambarius, įprastus namų daiktus, paprastus vietos žodžius, turėjimą ir daiktų prašymą.',
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
                'slug' => 'kambariai',
                'title' => 'Kambariai',
                'title_en' => 'Rooms',
                'subtitle' => 'Name a room at home',
                'subtitle_lt' => 'Pasakykite kambario pavadinimą',
                'description' => 'Say which room you are in or which room you see.',
                'description_lt' => 'Pasakykite, kuriame kambaryje esate arba kurį kambarį matote.',
                'emoji' => '🏠',
                'tone' => 'primary',
                'sort_order' => 71,
                'scene_slug' => 'kambarys',
                'goal_slug' => 'name-room',
                'goal_label' => 'Name a room',
                'goal_label_lt' => 'Pasakykite kambario pavadinimą',
                'goal_intent' => 'The learner names a room or says where they are at home.',
                'goal_intent_lt' => 'Mokinys pasako kambario pavadinimą arba kur yra namuose.',
                'example' => 'Aš esu virtuvėje.',
                'openings' => ['Kuriame kambaryje esate?', 'Pasakykite vieną namų kambarį.'],
                'replies' => ['Gerai. Kambarys aiškus.', 'Puiku. Supratau vietą.'],
                'note' => [
                    'en' => [
                        'This lesson starts home vocabulary with rooms.',
                        'Useful phrases:',
                        '- Virtuvė. = Kitchen.',
                        '- Kambarys. = Room.',
                        '- Vonios kambarys. = Bathroom.',
                        '- Aš esu virtuvėje. = I am in the kitchen.',
                        '- Aš esu kambaryje. = I am in the room.',
                        'Tiny grammar: many room words change when you mean in the room: virtuvė -> virtuvėje.',
                    ],
                    'lt' => [
                        'Ši pamoka pradeda namų žodyną nuo kambarių.',
                        'Naudingos frazės:',
                        '- Virtuvė.',
                        '- Kambarys.',
                        '- Vonios kambarys.',
                        '- Aš esu virtuvėje.',
                        '- Aš esu kambaryje.',
                        'Maža gramatikos pastaba: vietos žodžiai dažnai keičiasi, kai reiškia buvimą vietoje: virtuvė -> virtuvėje.',
                    ],
                ],
            ]),
            $this->scenario([
                'slug' => 'kur-yra-daiktas',
                'title' => 'Kur yra daiktas?',
                'title_en' => 'Where Is the Object?',
                'subtitle' => 'Say where an object is',
                'subtitle_lt' => 'Pasakykite, kur yra daiktas',
                'description' => 'Use here, there, on the table, or in the room.',
                'description_lt' => 'Vartokite čia, ten, ant stalo arba kambaryje.',
                'emoji' => '📍',
                'tone' => 'mint',
                'sort_order' => 72,
                'scene_slug' => 'vieta',
                'goal_slug' => 'say-object-location',
                'goal_label' => 'Say where it is',
                'goal_label_lt' => 'Pasakykite, kur yra',
                'goal_intent' => 'The learner says where a common object is using a simple location phrase.',
                'goal_intent_lt' => 'Mokinys paprasta vietos fraze pasako, kur yra įprastas daiktas.',
                'example' => 'Knyga yra ant stalo.',
                'openings' => ['Kur yra knyga?', 'Ar knyga yra čia ar ten?'],
                'replies' => ['Taip, vieta aiški.', 'Gerai. Daiktą radome.'],
                'note' => [
                    'en' => [
                        'This lesson practises simple object location.',
                        'Useful phrases:',
                        '- Kur yra knyga? = Where is the book?',
                        '- Knyga yra čia. = The book is here.',
                        '- Knyga yra ten. = The book is there.',
                        '- Knyga yra ant stalo. = The book is on the table.',
                        'Tiny grammar: ant means on.',
                    ],
                    'lt' => [
                        'Ši pamoka praktikuoja paprastą daikto vietą.',
                        'Naudingos frazės:',
                        '- Kur yra knyga?',
                        '- Knyga yra čia.',
                        '- Knyga yra ten.',
                        '- Knyga yra ant stalo.',
                        'Maža gramatikos pastaba: ant reiškia „on“.',
                    ],
                ],
            ]),
            $this->scenario([
                'slug' => 'ka-turite',
                'title' => 'Ką turite?',
                'title_en' => 'What Do You Have?',
                'subtitle' => 'Say what you have',
                'subtitle_lt' => 'Pasakykite, ką turite',
                'description' => 'Say that you have or do not have a common object.',
                'description_lt' => 'Pasakykite, kad turite arba neturite įprastą daiktą.',
                'emoji' => '🔑',
                'tone' => 'sky',
                'sort_order' => 73,
                'scene_slug' => 'turimas-daiktas',
                'goal_slug' => 'say-have-object',
                'goal_label' => 'Say what you have',
                'goal_label_lt' => 'Pasakykite, ką turite',
                'goal_intent' => 'The learner says they have or do not have a common object.',
                'goal_intent_lt' => 'Mokinys pasako, kad turi arba neturi įprastą daiktą.',
                'example' => 'Aš turiu raktą.',
                'openings' => ['Ką turite?', 'Ar turite raktą arba telefoną?'],
                'replies' => ['Gerai. Supratau, ką turite.', 'Puiku. Atsakymas aiškus.'],
                'note' => [
                    'en' => [
                        'This lesson practises possession.',
                        'Useful phrases:',
                        '- Aš turiu raktą. = I have a key.',
                        '- Aš turiu telefoną. = I have a phone.',
                        '- Neturiu rakto. = I do not have a key.',
                        '- Ar turite telefoną? = Do you have a phone?',
                        'Tiny grammar: turiu means I have. Neturiu means I do not have.',
                    ],
                    'lt' => [
                        'Ši pamoka praktikuoja turėjimą.',
                        'Naudingos frazės:',
                        '- Aš turiu raktą.',
                        '- Aš turiu telefoną.',
                        '- Neturiu rakto.',
                        '- Ar turite telefoną?',
                        'Maža gramatikos pastaba: turiu reiškia „I have“. Neturiu reiškia „I do not have“.',
                    ],
                ],
            ]),
            $this->scenario([
                'slug' => 'mano-kambarys',
                'title' => 'Mano kambarys',
                'title_en' => 'My Room',
                'subtitle' => 'Describe one thing in a room',
                'subtitle_lt' => 'Apibūdinkite vieną dalyką kambaryje',
                'description' => 'Say one simple sentence about your room.',
                'description_lt' => 'Pasakykite vieną paprastą sakinį apie savo kambarį.',
                'emoji' => '🛏️',
                'tone' => 'amber',
                'sort_order' => 74,
                'scene_slug' => 'aprasymas',
                'goal_slug' => 'describe-room',
                'goal_label' => 'Describe your room',
                'goal_label_lt' => 'Apibūdinkite savo kambarį',
                'goal_intent' => 'The learner says one simple thing about a room or object in a room.',
                'goal_intent_lt' => 'Mokinys pasako vieną paprastą dalyką apie kambarį arba daiktą kambaryje.',
                'example' => 'Mano kambaryje yra stalas.',
                'openings' => ['Koks jūsų kambarys?', 'Pasakykite vieną sakinį apie savo kambarį.'],
                'replies' => ['Gerai. Kambario aprašymas aiškus.', 'Puiku. Tai geras trumpas sakinys.'],
                'note' => [
                    'en' => [
                        'This lesson helps you describe a room with one sentence.',
                        'Useful phrases:',
                        '- Mano kambarys mažas. = My room is small.',
                        '- Mano kambarys didelis. = My room is big.',
                        '- Kambaryje yra stalas. = There is a table in the room.',
                        '- Kambaryje yra lova. = There is a bed in the room.',
                        'Useful pattern: yra means is/there is.',
                    ],
                    'lt' => [
                        'Ši pamoka padeda vienu sakiniu apibūdinti kambarį.',
                        'Naudingos frazės:',
                        '- Mano kambarys mažas.',
                        '- Mano kambarys didelis.',
                        '- Kambaryje yra stalas.',
                        '- Kambaryje yra lova.',
                        'Naudingas modelis: yra reiškia „is/there is“.',
                    ],
                ],
            ]),
            $this->scenario([
                'slug' => 'prasau-daikto',
                'title' => 'Prašau daikto',
                'title_en' => 'Asking for an Object',
                'subtitle' => 'Ask for something politely',
                'subtitle_lt' => 'Mandagiai paprašykite daikto',
                'description' => 'Ask for a key, phone, glass, or chair.',
                'description_lt' => 'Paprašykite rakto, telefono, stiklinės arba kėdės.',
                'emoji' => '🙏',
                'tone' => 'berry',
                'sort_order' => 75,
                'scene_slug' => 'prasymas',
                'goal_slug' => 'ask-for-object',
                'goal_label' => 'Ask for an object',
                'goal_label_lt' => 'Paprašykite daikto',
                'goal_intent' => 'The learner politely asks for a common object.',
                'goal_intent_lt' => 'Mokinys mandagiai paprašo įprasto daikto.',
                'example' => 'Prašau rakto.',
                'openings' => ['Ko jums reikia?', 'Kokio daikto norite?'],
                'replies' => ['Žinoma. Prašom.', 'Gerai. Tuoj paduosiu.'],
                'note' => [
                    'en' => [
                        'This lesson practises asking for things politely.',
                        'Useful phrases:',
                        '- Prašau rakto. = The key, please.',
                        '- Prašau telefono. = The phone, please.',
                        '- Prašau stiklinės. = A glass, please.',
                        '- Ar galite paduoti kėdę? = Can you pass the chair?',
                        'Tip: prašau can make a very short request polite.',
                    ],
                    'lt' => [
                        'Ši pamoka praktikuoja mandagų daiktų prašymą.',
                        'Naudingos frazės:',
                        '- Prašau rakto.',
                        '- Prašau telefono.',
                        '- Prašau stiklinės.',
                        '- Ar galite paduoti kėdę?',
                        'Patarimas: prašau trumpą prašymą padaro mandagų.',
                    ],
                ],
            ]),
            [
                'slug' => 'namu-pokalbis',
                'title' => 'Namų pokalbis',
                'subtitle' => 'Put home phrases together',
                'description' => 'Name a room, say where an object is, and ask for something politely.',
                'emoji' => '🛋️',
                'tone' => 'primary',
                'sort_order' => 76,
                'start_scene' => 'kambarys',
                'translations' => [
                    'title' => ['en' => 'Home Conversation', 'lt' => 'Namų pokalbis'],
                    'subtitle' => ['en' => 'Put home phrases together', 'lt' => 'Sujunkite namų frazes'],
                    'description' => ['en' => 'Name a room, say where an object is, and ask for something politely.', 'lt' => 'Pasakykite kambario pavadinimą, kur yra daiktas, ir mandagiai ko nors paprašykite.'],
                ],
                'note' => [
                    'title' => 'Before: Home Conversation',
                    'body' => implode("\n\n", [
                        'This capstone combines the home and object phrases from the unit.',
                        'Useful flow:',
                        '- Aš esu virtuvėje. = I am in the kitchen.',
                        '- Knyga yra ant stalo. = The book is on the table.',
                        '- Aš turiu raktą. = I have a key.',
                        '- Prašau telefono. = The phone, please.',
                        'Goal: use short phrases to handle a small home conversation.',
                    ]),
                    'translations' => [
                        'title' => ['en' => 'Before: Home Conversation', 'lt' => 'Prieš scenarijų: Namų pokalbis'],
                        'body' => ['en' => implode("\n\n", [
                            'This capstone combines the home and object phrases from the unit.',
                            'Useful flow:',
                            '- Aš esu virtuvėje. = I am in the kitchen.',
                            '- Knyga yra ant stalo. = The book is on the table.',
                            '- Aš turiu raktą. = I have a key.',
                            '- Prašau telefono. = The phone, please.',
                            'Goal: use short phrases to handle a small home conversation.',
                        ]), 'lt' => implode("\n\n", [
                            'Šis pakartojimo scenarijus sujungia namų ir daiktų frazes iš šio skyriaus.',
                            'Naudinga seka:',
                            '- Aš esu virtuvėje.',
                            '- Knyga yra ant stalo.',
                            '- Aš turiu raktą.',
                            '- Prašau telefono.',
                            'Tikslas: trumpomis frazėmis atlikite mažą namų pokalbį.',
                        ])],
                    ],
                ],
                'scenes' => [
                    $this->capstoneScene('kambarys', 'Room', 'Kambarys', 'Gabija asks which room the learner is in.', 'Gabija klausia, kuriame kambaryje mokinys yra.', ['Kuriame kambaryje esate?', 'Pasakykite, kur esate namuose.'], ['Gerai. Kambarys aiškus.', 'Puiku. Supratau vietą.'], 'capstone-room', 'Name the room', 'Pasakykite kambarį', 'The learner names a room or says where they are at home.', 'Mokinys pasako kambario pavadinimą arba kur yra namuose.', 'Aš esu virtuvėje.', 'daikto-vieta'),
                    $this->capstoneScene('daikto-vieta', 'Object location', 'Daikto vieta', 'Gabija asks where a common object is.', 'Gabija klausia, kur yra įprastas daiktas.', ['Kur yra knyga?', 'Kur yra telefonas?'], ['Taip, vieta aiški.', 'Gerai. Daiktą radome.'], 'capstone-object-location', 'Say where it is', 'Pasakykite, kur yra', 'The learner says where a common object is using a short location phrase.', 'Mokinys trumpa vietos fraze pasako, kur yra įprastas daiktas.', 'Knyga yra ant stalo.', 'prasymas'),
                    $this->capstoneScene('prasymas', 'Polite request', 'Mandagus prašymas', 'Gabija asks what object the learner needs.', 'Gabija klausia, kokio daikto mokiniui reikia.', ['Ko jums reikia?', 'Kokio daikto norite?'], ['Žinoma. Prašom.', 'Labai gerai. Mandagus prašymas.'], 'capstone-object-request', 'Ask for something', 'Paprašykite daikto', 'The learner politely asks for a common object.', 'Mokinys mandagiai paprašo įprasto daikto.', 'Prašau telefono.', null),
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
            'Kuriame kambaryje esate?' => 'Which room are you in?',
            'Pasakykite vieną namų kambarį.' => 'Say one room at home.',
            'Gerai. Kambarys aiškus.' => 'Good. The room is clear.',
            'Puiku. Supratau vietą.' => 'Great. I understood the place.',
            'Kur yra knyga?' => 'Where is the book?',
            'Ar knyga yra čia ar ten?' => 'Is the book here or there?',
            'Taip, vieta aiški.' => 'Yes, the place is clear.',
            'Gerai. Daiktą radome.' => 'Good. We found the object.',
            'Ką turite?' => 'What do you have?',
            'Ar turite raktą arba telefoną?' => 'Do you have a key or a phone?',
            'Gerai. Supratau, ką turite.' => 'Good. I understood what you have.',
            'Puiku. Atsakymas aiškus.' => 'Great. The answer is clear.',
            'Koks jūsų kambarys?' => 'What is your room like?',
            'Pasakykite vieną sakinį apie savo kambarį.' => 'Say one sentence about your room.',
            'Gerai. Kambario aprašymas aiškus.' => 'Good. The room description is clear.',
            'Puiku. Tai geras trumpas sakinys.' => 'Great. That is a good short sentence.',
            'Ko jums reikia?' => 'What do you need?',
            'Kokio daikto norite?' => 'What object do you want?',
            'Žinoma. Prašom.' => 'Of course. Here you are.',
            'Gerai. Tuoj paduosiu.' => 'Good. I will pass it now.',
            'Pasakykite, kur esate namuose.' => 'Say where you are at home.',
            'Kur yra telefonas?' => 'Where is the phone?',
            'Labai gerai. Mandagus prašymas.' => 'Very good. A polite request.',
            default => $line,
        };
    }
}
