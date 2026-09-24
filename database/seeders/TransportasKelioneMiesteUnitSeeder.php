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

class TransportasKelioneMiesteUnitSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $language = Language::query()->where('code', 'lt')->firstOrFail();
            $character = Character::query()->where('slug', 'gabija')->firstOrFail();
            $unit = Unit::query()->updateOrCreate(
                ['slug' => 'transportas-ir-kelione-mieste'],
                [
                    'language_id' => $language->id,
                    'title' => 'Transportas ir kelionė mieste',
                    'description' => 'A practical unit for tickets, destinations, buses, stops, taxis, and short city travel questions.',
                    'cefr_level' => 'a1',
                    'status' => ContentStatus::Published,
                    'sort_order' => 90,
                    'published_at' => now(),
                ],
            );

            $this->syncTranslations($unit, [
                'title' => ['en' => 'Transport and City Travel', 'lt' => 'Transportas ir kelionė mieste'],
                'description' => [
                    'en' => 'A practical unit for tickets, destinations, buses, stops, taxis, and short city travel questions.',
                    'lt' => 'Praktiškas skyrius apie bilietus, kelionės tikslus, autobusus, stoteles, taksi ir trumpus kelionės mieste klausimus.',
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
                'slug' => 'bilietas',
                'title' => 'Bilietas',
                'title_en' => 'A Ticket',
                'subtitle' => 'Ask for one ticket',
                'subtitle_lt' => 'Paprašykite vieno bilieto',
                'description' => 'Ask for one ticket to a simple destination.',
                'description_lt' => 'Paprašykite vieno bilieto į paprastą kelionės tikslą.',
                'emoji' => '🎫',
                'tone' => 'primary',
                'sort_order' => 91,
                'scene_slug' => 'bilieto-prasymas',
                'goal_slug' => 'ask-ticket',
                'goal_label' => 'Ask for a ticket',
                'goal_label_lt' => 'Paprašykite bilieto',
                'goal_intent' => 'The learner asks for one ticket to a simple destination.',
                'goal_intent_lt' => 'Mokinys paprašo vieno bilieto į paprastą kelionės tikslą.',
                'example' => 'Vieną bilietą į centrą, prašau.',
                'openings' => ['Kur važiuosite?', 'Kokio bilieto reikia?'],
                'replies' => ['Gerai. Vienas bilietas.', 'Prašom, jūsų bilietas.'],
                'note' => [
                    'en' => [
                        'This lesson starts transport with a ticket request.',
                        'Useful phrases:',
                        '- Vieną bilietą, prašau. = One ticket, please.',
                        '- Vieną bilietą į centrą, prašau. = One ticket to the center, please.',
                        '- Vieną bilietą į stotį, prašau. = One ticket to the station, please.',
                        '- Ačiū. = Thank you.',
                        'Tiny grammar: į means to when you say a destination.',
                    ],
                    'lt' => [
                        'Ši pamoka pradeda transporto temą nuo bilieto prašymo.',
                        'Naudingos frazės:',
                        '- Vieną bilietą, prašau.',
                        '- Vieną bilietą į centrą, prašau.',
                        '- Vieną bilietą į stotį, prašau.',
                        '- Ačiū.',
                        'Maža gramatikos pastaba: į vartojame kelionės tikslui pasakyti.',
                    ],
                ],
            ]),
            $this->scenario([
                'slug' => 'kur-vaziuojate',
                'title' => 'Kur važiuojate?',
                'title_en' => 'Where Are You Going?',
                'subtitle' => 'Say your destination',
                'subtitle_lt' => 'Pasakykite kelionės tikslą',
                'description' => 'Say that you are going to the center, station, airport, or home.',
                'description_lt' => 'Pasakykite, kad važiuojate į centrą, stotį, oro uostą arba namo.',
                'emoji' => '📍',
                'tone' => 'mint',
                'sort_order' => 92,
                'scene_slug' => 'tikslas',
                'goal_slug' => 'say-destination',
                'goal_label' => 'Say your destination',
                'goal_label_lt' => 'Pasakykite kelionės tikslą',
                'goal_intent' => 'The learner says where they are going in the city.',
                'goal_intent_lt' => 'Mokinys pasako, kur važiuoja mieste.',
                'example' => 'Važiuoju į centrą.',
                'openings' => ['Kur jūs važiuojate?', 'Pasakykite, kur važiuojate.'],
                'replies' => ['Gerai. Kelionės tikslas aiškus.', 'Puiku. Supratau kryptį.'],
                'note' => [
                    'en' => [
                        'This lesson practises saying your destination.',
                        'Useful phrases:',
                        '- Važiuoju į centrą. = I am going to the center.',
                        '- Važiuoju į stotį. = I am going to the station.',
                        '- Važiuoju į oro uostą. = I am going to the airport.',
                        '- Važiuoju namo. = I am going home.',
                        'Transport word: važiuoju means I go by transport.',
                    ],
                    'lt' => [
                        'Ši pamoka praktikuoja kelionės tikslo pasakymą.',
                        'Naudingos frazės:',
                        '- Važiuoju į centrą.',
                        '- Važiuoju į stotį.',
                        '- Važiuoju į oro uostą.',
                        '- Važiuoju namo.',
                        'Transporto žodis: važiuoju reiškia judėjimą transportu.',
                    ],
                ],
            ]),
            $this->scenario([
                'slug' => 'kuris-autobusas',
                'title' => 'Kuris autobusas?',
                'title_en' => 'Which Bus?',
                'subtitle' => 'Ask which bus to take',
                'subtitle_lt' => 'Paklauskite, kuris autobusas tinka',
                'description' => 'Ask which bus goes to a place.',
                'description_lt' => 'Paklauskite, kuris autobusas važiuoja į vietą.',
                'emoji' => '🚌',
                'tone' => 'sky',
                'sort_order' => 93,
                'scene_slug' => 'autobusas',
                'goal_slug' => 'ask-which-bus',
                'goal_label' => 'Ask which bus',
                'goal_label_lt' => 'Paklauskite, kuris autobusas',
                'goal_intent' => 'The learner asks which bus goes to a simple destination.',
                'goal_intent_lt' => 'Mokinys paklausia, kuris autobusas važiuoja į paprastą kelionės tikslą.',
                'example' => 'Kuris autobusas važiuoja į centrą?',
                'openings' => ['Kur norite važiuoti?', 'Galiu padėti su autobusu.'],
                'replies' => ['Jums tinka antras autobusas.', 'Jums tinka trečias autobusas.'],
                'note' => [
                    'en' => [
                        'This lesson practises asking about buses.',
                        'Useful phrases:',
                        '- Kuris autobusas? = Which bus?',
                        '- Kuris autobusas važiuoja į centrą? = Which bus goes to the center?',
                        '- Antras autobusas. = Bus number two.',
                        '- Trečias autobusas. = Bus number three.',
                        'Tip: keep the question short. It is enough at A1.',
                    ],
                    'lt' => [
                        'Ši pamoka praktikuoja klausimus apie autobusus.',
                        'Naudingos frazės:',
                        '- Kuris autobusas?',
                        '- Kuris autobusas važiuoja į centrą?',
                        '- Antras autobusas.',
                        '- Trečias autobusas.',
                        'Patarimas: klausimą laikykite trumpą. A1 lygiui to pakanka.',
                    ],
                ],
            ]),
            $this->scenario([
                'slug' => 'kada-isvyksta',
                'title' => 'Kada išvyksta?',
                'title_en' => 'When Does It Leave?',
                'subtitle' => 'Ask a simple departure time',
                'subtitle_lt' => 'Paklauskite paprasto išvykimo laiko',
                'description' => 'Ask when a bus or train leaves.',
                'description_lt' => 'Paklauskite, kada išvyksta autobusas arba traukinys.',
                'emoji' => '🕘',
                'tone' => 'amber',
                'sort_order' => 94,
                'scene_slug' => 'laikas',
                'goal_slug' => 'ask-departure-time',
                'goal_label' => 'Ask when it leaves',
                'goal_label_lt' => 'Paklauskite, kada išvyksta',
                'goal_intent' => 'The learner asks when a bus or train leaves.',
                'goal_intent_lt' => 'Mokinys paklausia, kada išvyksta autobusas arba traukinys.',
                'example' => 'Kada išvyksta autobusas?',
                'openings' => ['Kokio laiko norite paklausti?', 'Autobusas išvyksta vėliau. Ko paklausite?'],
                'replies' => ['Autobusas išvyksta trečią valandą.', 'Traukinys išvyksta penktą valandą.'],
                'note' => [
                    'en' => [
                        'This lesson connects transport and time.',
                        'Useful phrases:',
                        '- Kada išvyksta autobusas? = When does the bus leave?',
                        '- Kada išvyksta traukinys? = When does the train leave?',
                        '- Trečią valandą. = At three o’clock.',
                        '- Penktą valandą. = At five o’clock.',
                        'Focus: one clear time question is enough.',
                    ],
                    'lt' => [
                        'Ši pamoka sujungia transportą ir laiką.',
                        'Naudingos frazės:',
                        '- Kada išvyksta autobusas?',
                        '- Kada išvyksta traukinys?',
                        '- Trečią valandą.',
                        '- Penktą valandą.',
                        'Svarbu: užtenka vieno aiškaus klausimo apie laiką.',
                    ],
                ],
            ]),
            $this->scenario([
                'slug' => 'taksi',
                'title' => 'Taksi',
                'title_en' => 'Taxi',
                'subtitle' => 'Say you need a taxi',
                'subtitle_lt' => 'Pasakykite, kad reikia taksi',
                'description' => 'Ask for a taxi and say where you want to go.',
                'description_lt' => 'Paprašykite taksi ir pasakykite, kur norite važiuoti.',
                'emoji' => '🚕',
                'tone' => 'berry',
                'sort_order' => 95,
                'scene_slug' => 'taksi',
                'goal_slug' => 'ask-taxi',
                'goal_label' => 'Ask for a taxi',
                'goal_label_lt' => 'Paprašykite taksi',
                'goal_intent' => 'The learner says they need a taxi or gives a simple taxi destination.',
                'goal_intent_lt' => 'Mokinys pasako, kad reikia taksi, arba pasako paprastą kelionės tikslą taksi.',
                'example' => 'Man reikia taksi į stotį.',
                'openings' => ['Ar jums reikia taksi?', 'Kur važiuosite taksi?'],
                'replies' => ['Gerai. Taksi atvyks netrukus.', 'Supratau. Kelionės tikslas aiškus.'],
                'note' => [
                    'en' => [
                        'This lesson gives one useful taxi phrase.',
                        'Useful phrases:',
                        '- Man reikia taksi. = I need a taxi.',
                        '- Man reikia taksi į stotį. = I need a taxi to the station.',
                        '- Važiuoju į viešbutį. = I am going to the hotel.',
                        '- Kiek kainuoja? = How much does it cost?',
                        'Goal: say the need and destination simply.',
                    ],
                    'lt' => [
                        'Ši pamoka duoda vieną naudingą taksi frazę.',
                        'Naudingos frazės:',
                        '- Man reikia taksi.',
                        '- Man reikia taksi į stotį.',
                        '- Važiuoju į viešbutį.',
                        '- Kiek kainuoja?',
                        'Tikslas: paprastai pasakykite poreikį ir kelionės tikslą.',
                    ],
                ],
            ]),
            [
                'slug' => 'kelione-mieste',
                'title' => 'Kelionė mieste',
                'subtitle' => 'Put city travel phrases together',
                'description' => 'Ask for a ticket, say your destination, ask when to leave, and finish politely.',
                'emoji' => '🧭',
                'tone' => 'primary',
                'sort_order' => 96,
                'start_scene' => 'tikslas',
                'translations' => [
                    'title' => ['en' => 'City Travel', 'lt' => 'Kelionė mieste'],
                    'subtitle' => ['en' => 'Put city travel phrases together', 'lt' => 'Sujunkite kelionės mieste frazes'],
                    'description' => ['en' => 'Ask for a ticket, say your destination, ask when to leave, and finish politely.', 'lt' => 'Paprašykite bilieto, pasakykite kelionės tikslą, paklauskite išvykimo laiko ir mandagiai užbaikite.'],
                ],
                'note' => [
                    'title' => 'Before: City Travel',
                    'body' => implode("\n\n", [
                        'This capstone combines the transport phrases from the unit.',
                        'Useful flow:',
                        '- Važiuoju į centrą. = I am going to the center.',
                        '- Vieną bilietą, prašau. = One ticket, please.',
                        '- Kada išvyksta autobusas? = When does the bus leave?',
                        '- Ačiū, viso gero. = Thank you, goodbye.',
                        'Goal: handle a small city travel exchange in short steps.',
                    ]),
                    'translations' => [
                        'title' => ['en' => 'Before: City Travel', 'lt' => 'Prieš scenarijų: Kelionė mieste'],
                        'body' => ['en' => implode("\n\n", [
                            'This capstone combines the transport phrases from the unit.',
                            'Useful flow:',
                            '- Važiuoju į centrą. = I am going to the center.',
                            '- Vieną bilietą, prašau. = One ticket, please.',
                            '- Kada išvyksta autobusas? = When does the bus leave?',
                            '- Ačiū, viso gero. = Thank you, goodbye.',
                            'Goal: handle a small city travel exchange in short steps.',
                        ]), 'lt' => implode("\n\n", [
                            'Šis pakartojimo scenarijus sujungia transporto frazes iš šio skyriaus.',
                            'Naudinga seka:',
                            '- Važiuoju į centrą.',
                            '- Vieną bilietą, prašau.',
                            '- Kada išvyksta autobusas?',
                            '- Ačiū, viso gero.',
                            'Tikslas: trumpais žingsniais atlikite mažą kelionės mieste pokalbį.',
                        ])],
                    ],
                ],
                'scenes' => [
                    $this->capstoneScene('tikslas', 'Destination', 'Kelionės tikslas', 'Gabija asks where the learner is going.', 'Gabija klausia, kur mokinys važiuoja.', ['Kur važiuosite?', 'Koks jūsų kelionės tikslas?'], ['Gerai. Tikslas aiškus.', 'Puiku. Supratau kryptį.'], 'capstone-destination', 'Say the destination', 'Pasakykite kelionės tikslą', 'The learner says where they are going in the city.', 'Mokinys pasako, kur važiuoja mieste.', 'Važiuoju į centrą.', 'bilietas'),
                    $this->capstoneScene('bilietas', 'Ticket', 'Bilietas', 'Gabija asks what ticket the learner needs.', 'Gabija klausia, kokio bilieto mokiniui reikia.', ['Kokio bilieto reikia?', 'Ar reikia vieno bilieto?'], ['Gerai. Vienas bilietas.', 'Prašom, jūsų bilietas.'], 'capstone-ticket', 'Ask for a ticket', 'Paprašykite bilieto', 'The learner asks for one ticket.', 'Mokinys paprašo vieno bilieto.', 'Vieną bilietą, prašau.', 'laikas'),
                    $this->capstoneScene('laikas', 'Departure time', 'Išvykimo laikas', 'Gabija waits for a simple departure-time question.', 'Gabija laukia paprasto klausimo apie išvykimo laiką.', ['Autobusas išvyksta netrukus.', 'Ko dar norite paklausti?'], ['Autobusas išvyksta trečią valandą.', 'Gerai. Išvykimo laiką pasakiau.'], 'capstone-time', 'Ask departure time', 'Paklauskite išvykimo laiko', 'The learner asks when the bus leaves or thanks Gabija for the information.', 'Mokinys paklausia, kada išvyksta autobusas, arba padėkoja už informaciją.', 'Kada išvyksta autobusas?', null),
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
            'Kur važiuosite?' => 'Where are you going?',
            'Kokio bilieto reikia?' => 'What ticket do you need?',
            'Gerai. Vienas bilietas.' => 'Good. One ticket.',
            'Prašom, jūsų bilietas.' => 'Here is your ticket.',
            'Kur jūs važiuojate?' => 'Where are you going?',
            'Pasakykite, kur važiuojate.' => 'Say where you are going.',
            'Gerai. Kelionės tikslas aiškus.' => 'Good. The destination is clear.',
            'Puiku. Supratau kryptį.' => 'Great. I understood the direction.',
            'Kur norite važiuoti?' => 'Where do you want to go?',
            'Galiu padėti su autobusu.' => 'I can help with the bus.',
            'Jums tinka antras autobusas.' => 'Bus number two works for you.',
            'Jums tinka trečias autobusas.' => 'Bus number three works for you.',
            'Kokio laiko norite paklausti?' => 'What time do you want to ask about?',
            'Autobusas išvyksta vėliau. Ko paklausite?' => 'The bus leaves later. What will you ask?',
            'Autobusas išvyksta trečią valandą.' => 'The bus leaves at three o’clock.',
            'Traukinys išvyksta penktą valandą.' => 'The train leaves at five o’clock.',
            'Ar jums reikia taksi?' => 'Do you need a taxi?',
            'Kur važiuosite taksi?' => 'Where will you go by taxi?',
            'Gerai. Taksi atvyks netrukus.' => 'Good. The taxi will arrive soon.',
            'Supratau. Kelionės tikslas aiškus.' => 'I understand. The destination is clear.',
            'Koks jūsų kelionės tikslas?' => 'What is your destination?',
            'Gerai. Tikslas aiškus.' => 'Good. The destination is clear.',
            'Ar reikia vieno bilieto?' => 'Do you need one ticket?',
            'Autobusas išvyksta netrukus.' => 'The bus leaves soon.',
            'Ko dar norite paklausti?' => 'What else do you want to ask?',
            'Gerai. Išvykimo laiką pasakiau.' => 'Good. I gave the departure time.',
            default => $line,
        };
    }
}
