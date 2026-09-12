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

class KurVietosIrKryptysUnitSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $language = Language::query()->where('code', 'lt')->firstOrFail();
            $character = Character::query()->where('slug', 'gabija')->firstOrFail();
            $unit = Unit::query()->updateOrCreate(
                ['slug' => 'kur-vietos-ir-kryptys'],
                [
                    'language_id' => $language->id,
                    'title' => 'Kur? Vietos ir kryptys',
                    'description' => 'A survival unit for saying where you are, asking where a place is, and understanding very simple directions.',
                    'cefr_level' => 'a1',
                    'status' => ContentStatus::Published,
                    'sort_order' => 30,
                    'published_at' => now(),
                ],
            );

            $this->syncTranslations($unit, [
                'title' => ['en' => 'Where? Places and Directions', 'lt' => 'Kur? Vietos ir kryptys'],
                'description' => [
                    'en' => 'A survival unit for saying where you are, asking where a place is, and understanding very simple directions.',
                    'lt' => 'Praktiškas skyrius apie tai, kur esate, kur yra vieta, ir kaip suprasti labai paprastas kryptis.',
                ],
            ]);

            foreach ($this->scenarios() as $scenarioData) {
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
                $scenes = $this->upsertScenes($scenario, $scenarioData['scenes']);
                $this->replaceSceneContent($scenes, $scenarioData['scenes']);
                $this->deleteRemovedScenes($scenario, $scenarioData['scenes']);
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
            $model = $scenes->get($scene['slug']);

            foreach ($scene['goals'] as $index => $goal) {
                $goalModel = $model->goals()->updateOrCreate(
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

            $model->goals()->whereNotIn('slug', collect($scene['goals'])->pluck('slug')->all())->delete();
        }

        $goals = Goal::query()->whereIn('scene_id', $scenes->pluck('id'))->get()->keyBy('slug');

        foreach ($sceneData as $scene) {
            $model = $scenes->get($scene['slug']);
            $model->npcLines()->delete();
            $model->props()->delete();

            foreach ($scene['lines'] as $index => $line) {
                $npcLine = $model->npcLines()->create([
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
            $this->scenario('kur-as-esu', 'Kur aš esu?', 'Say where you are', 'Say that you are at home, work, school, a cafe, or in Vilnius.', '📍', 'primary', 21, 'vieta', 'Where Am I?', 'Aš esu kavinėje.', [
                'Labas! Kur jūs esate?',
                'Sveiki. Pasakykite, kur esate.',
            ], [
                'Puiku, supratau.',
                'Gerai. Dabar žinau, kur esate.',
            ], 'say-where-you-are', 'Say where you are', 'The learner says where they are using a short phrase such as at home, at work, at school, at a cafe, in the city, or in Vilnius.'),
            $this->scenario('kur-yra-tualetas', 'Kur yra tualetas?', 'Ask where something is', 'Ask where the toilet, cash desk, or exit is.', '🚻', 'sky', 22, 'klausimas', 'Where Is the Toilet?', 'Kur yra tualetas?', [
                'Atsiprašau, ko ieškote?',
                'Sveiki. Galiu padėti?',
            ], [
                'Tualetas yra ten.',
                'Žinoma. Tualetas yra dešinėje.',
            ], 'ask-where-place-is', 'Ask where a place is', 'The learner politely asks where a basic place or object is, such as the toilet, cash desk, exit, station, or pharmacy.'),
            $this->scenario('cia-ar-ten', 'Čia ar ten?', 'Answer with place words', 'Use simple words like here, there, left, and right.', '↔️', 'mint', 23, 'zodziai', 'Here or There?', 'Ten, dešinėje.', [
                'Kur yra kasa: čia ar ten?',
                'Ar vaistinė yra kairėje ar dešinėje?',
            ], [
                'Taip, ten.',
                'Gerai. Dešinėje.',
            ], 'answer-simple-location', 'Answer with a place word', 'The learner answers with a very short location word or phrase: here, there, left, or right.'),
            $this->scenario('mieste-kur-yra', 'Mieste: kur yra?', 'Ask in town', 'Ask where a station, pharmacy, cafe, or bank is in town.', '🏙️', 'berry', 24, 'miestas', 'In Town: Where Is It?', 'Atsiprašau, kur yra stotis?', [
                'Laba diena. Jūs esate mieste.',
                'Sveiki. Ko ieškote mieste?',
            ], [
                'Stotis yra ten, tiesiai.',
                'Vaistinė yra čia, kairėje.',
            ], 'ask-in-town', 'Ask where a public place is', 'The learner politely asks where one public place is in town.'),
            $this->scenario('suprantu-krypti', 'Suprantu kryptį', 'React to directions', 'Show that you understand a very simple direction.', '🧭', 'amber', 25, 'kryptis', 'I Understand the Direction', 'Gerai, ačiū. Supratau.', [
                'Eikite tiesiai.',
                'Pasukite į kairę. Vaistinė yra ten.',
            ], [
                'Puiku. Geros dienos!',
                'Labai gerai. Iki!',
            ], 'acknowledge-direction', 'Say you understand', 'The learner acknowledges a simple direction with thanks or says they understand.'),
            $this->scenario('trumpa-pagalba-mieste', 'Trumpa pagalba mieste', 'City help capstone', 'Ask where a place is, understand the answer, and say thank you.', '🗺️', 'primary', 26, 'pagalba', 'Short Help in Town', 'Atsiprašau, kur yra vaistinė?', [
                'Laba diena. Ar jums reikia pagalbos?',
                'Sveiki. Ko ieškote?',
            ], [
                'Vaistinė yra ten, kairėje.',
                'Žinoma. Eikite tiesiai.',
            ], 'ask-for-city-help', 'Ask for city help', 'The learner politely asks where a public place is.'),
        ];
    }

    /**
     * @param  array<int, string>  $openings
     * @param  array<int, string>  $replies
     * @return array<string, mixed>
     */
    private function scenario(
        string $slug,
        string $title,
        string $subtitle,
        string $description,
        string $emoji,
        string $tone,
        int $sortOrder,
        string $sceneSlug,
        string $englishTitle,
        string $example,
        array $openings,
        array $replies,
        string $goalSlug,
        string $goalLabel,
        string $goalIntent,
    ): array {
        $lines = collect($openings)
            ->map(fn (string $line): array => ['target_text' => $line, 'support_translation' => $this->translateLine($line)])
            ->merge(collect($replies)->map(fn (string $line): array => [
                'target_text' => $line,
                'support_translation' => $this->translateLine($line),
                'trigger_goal' => $goalSlug,
                'priority' => 100,
            ]))
            ->all();

        return [
            'slug' => $slug,
            'title' => $title,
            'subtitle' => $subtitle,
            'description' => $description,
            'emoji' => $emoji,
            'tone' => $tone,
            'sort_order' => $sortOrder,
            'start_scene' => $sceneSlug,
            'translations' => [
                'title' => ['en' => $englishTitle, 'lt' => $title],
                'subtitle' => ['en' => $subtitle, 'lt' => $subtitle],
                'description' => ['en' => $description, 'lt' => $description],
            ],
            'note' => $this->note($slug, $title, $englishTitle, $example),
            'scenes' => [[
                'slug' => $sceneSlug,
                'title' => $title,
                'setting' => $description,
                'translations' => [
                    'title' => ['en' => $englishTitle, 'lt' => $title],
                    'setting' => ['en' => $description, 'lt' => $description],
                ],
                'lines' => $lines,
                'goals' => [[
                    'slug' => $goalSlug,
                    'label' => $goalLabel,
                    'intent' => $goalIntent,
                    'example' => $example,
                    'next' => null,
                    'translations' => [
                        'label' => ['en' => $goalLabel, 'lt' => $goalLabel],
                        'intent' => ['en' => $goalIntent, 'lt' => $goalIntent],
                    ],
                ]],
            ]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function note(string $slug, string $title, string $englishTitle, string $example): array
    {
        [$englishBody, $lithuanianBody] = match ($slug) {
            'kur-as-esu' => [
                implode("\n\n", [
                    'This lesson helps you say where you are.',
                    'Useful chunks:',
                    '- Aš esu kavinėje. = I am at the cafe.',
                    '- Aš esu parduotuvėje. = I am at the shop.',
                    '- Aš esu mokykloje. = I am at school.',
                    '- Aš esu Vilniuje. = I am in Vilnius.',
                    '- Aš esu namie. = I am at home.',
                    'Tiny grammar: many place words change when you mean “in/at”: kavinė → kavinėje, parduotuvė → parduotuvėje, mokykla → mokykloje. Learn them first as ready-made speaking chunks.',
                    "Model answer: {$example}",
                ]),
                implode("\n\n", [
                    'Ši pamoka padeda pasakyti, kur esate.',
                    'Naudingos frazės:',
                    '- Aš esu kavinėje.',
                    '- Aš esu parduotuvėje.',
                    '- Aš esu mokykloje.',
                    '- Aš esu Vilniuje.',
                    '- Aš esu namie.',
                    'Maža gramatikos pastaba: vietos žodžiai dažnai keičiasi, kai reiškia „in/at“: kavinė → kavinėje, parduotuvė → parduotuvėje, mokykla → mokykloje. Pirmiausia mokykitės juos kaip paruoštas kalbėjimo frazes.',
                    "Pavyzdys: {$example}",
                ]),
            ],
            'kur-yra-tualetas' => [
                implode("\n\n", [
                    'This lesson practises one very useful question: Kur yra ...?',
                    'Useful phrases:',
                    '- Atsiprašau. = Excuse me.',
                    '- Kur yra tualetas? = Where is the toilet?',
                    '- Kur yra kasa? = Where is the cash desk?',
                    '- Kur yra išėjimas? = Where is the exit?',
                    '- Ačiū. = Thank you.',
                    'Tiny grammar: in this question, the place you are looking for usually stays simple: tualetas, kasa, išėjimas. Do not worry about endings yet.',
                    "Model answer: {$example}",
                ]),
                implode("\n\n", [
                    'Ši pamoka praktikuoja vieną labai naudingą klausimą: Kur yra ...?',
                    'Naudingos frazės:',
                    '- Atsiprašau.',
                    '- Kur yra tualetas?',
                    '- Kur yra kasa?',
                    '- Kur yra išėjimas?',
                    '- Ačiū.',
                    'Maža gramatikos pastaba: šiame klausime ieškomas daiktas ar vieta dažniausiai lieka paprasta forma: tualetas, kasa, išėjimas. Dėl galūnių kol kas nesijaudinkite.',
                    "Pavyzdys: {$example}",
                ]),
            ],
            'cia-ar-ten' => [
                implode("\n\n", [
                    'This lesson is about very short location answers.',
                    'Useful words:',
                    '- Čia. = Here.',
                    '- Ten. = There.',
                    '- Kairėje. = On the left.',
                    '- Dešinėje. = On the right.',
                    '- Taip, ten. = Yes, there.',
                    'Tiny grammar: kairėje and dešinėje already include the idea “on the left/right.” Use them as complete short answers.',
                    "Model answer: {$example}",
                ]),
                implode("\n\n", [
                    'Ši pamoka yra apie labai trumpus atsakymus apie vietą.',
                    'Naudingi žodžiai:',
                    '- Čia.',
                    '- Ten.',
                    '- Kairėje.',
                    '- Dešinėje.',
                    '- Taip, ten.',
                    'Maža gramatikos pastaba: kairėje ir dešinėje jau reiškia „on the left/right“. Galite juos vartoti kaip trumpus pilnus atsakymus.',
                    "Pavyzdys: {$example}",
                ]),
            ],
            'mieste-kur-yra' => [
                implode("\n\n", [
                    'This lesson moves the same question into town.',
                    'Useful phrases:',
                    '- Atsiprašau, kur yra stotis? = Excuse me, where is the station?',
                    '- Kur yra vaistinė? = Where is the pharmacy?',
                    '- Kur yra kavinė? = Where is the cafe?',
                    '- Mieste. = In town.',
                    'Tiny grammar: miestas means town/city. Mieste means in town/in the city. Treat mieste as one useful place word for now.',
                    "Model answer: {$example}",
                ]),
                implode("\n\n", [
                    'Ši pamoka perkelia tą patį klausimą į miestą.',
                    'Naudingos frazės:',
                    '- Atsiprašau, kur yra stotis?',
                    '- Kur yra vaistinė?',
                    '- Kur yra kavinė?',
                    '- Mieste.',
                    'Maža gramatikos pastaba: miestas yra „town/city“. Mieste reiškia „in town/in the city“. Kol kas mokykitės mieste kaip vieną naudingą vietos žodį.',
                    "Pavyzdys: {$example}",
                ]),
            ],
            'suprantu-krypti' => [
                implode("\n\n", [
                    'This lesson is mostly listening and reacting. You do not need to give directions yourself yet.',
                    'Useful direction words:',
                    '- Tiesiai. = Straight ahead.',
                    '- Kairėje. = On the left.',
                    '- Dešinėje. = On the right.',
                    '- Gerai, ačiū. = Okay, thank you.',
                    '- Supratau. = I understood.',
                    'Tiny grammar: eikite and pasukite are polite instruction forms. At this stage, just recognize them.',
                    "Model answer: {$example}",
                ]),
                implode("\n\n", [
                    'Ši pamoka daugiausia apie klausymą ir reakciją. Jums dar nereikia pačiam aiškinti kelio.',
                    'Naudingi krypties žodžiai:',
                    '- Tiesiai.',
                    '- Kairėje.',
                    '- Dešinėje.',
                    '- Gerai, ačiū.',
                    '- Supratau.',
                    'Maža gramatikos pastaba: eikite ir pasukite yra mandagios nurodymų formos. Šiame etape svarbiausia jas atpažinti.',
                    "Pavyzdys: {$example}",
                ]),
            ],
            default => [
                implode("\n\n", [
                    'This capstone combines the location phrases from the unit.',
                    'Useful flow:',
                    '- Atsiprašau. = Excuse me.',
                    '- Kur yra vaistinė? = Where is the pharmacy?',
                    '- Ten, kairėje. = There, on the left.',
                    '- Gerai, ačiū. = Okay, thank you.',
                    'Tiny grammar: keep the full phrase simple. Your goal is to ask clearly and respond politely.',
                    "Model answer: {$example}",
                ]),
                implode("\n\n", [
                    'Šis pakartojimo scenarijus sujungia skyriaus vietos frazes.',
                    'Naudinga seka:',
                    '- Atsiprašau.',
                    '- Kur yra vaistinė?',
                    '- Ten, kairėje.',
                    '- Gerai, ačiū.',
                    'Maža gramatikos pastaba: laikykite visą frazę paprastą. Tikslas - aiškiai paklausti ir mandagiai sureaguoti.',
                    "Pavyzdys: {$example}",
                ]),
            ],
        };

        return [
            'title' => "Before: {$englishTitle}",
            'body' => $englishBody,
            'translations' => [
                'title' => ['en' => "Before: {$englishTitle}", 'lt' => "Prieš scenarijų: {$title}"],
                'body' => ['en' => $englishBody, 'lt' => $lithuanianBody],
            ],
        ];
    }

    private function translateLine(string $line): string
    {
        return match ($line) {
            'Labas! Kur jūs esate?' => 'Hi! Where are you?',
            'Sveiki. Pasakykite, kur esate.' => 'Hello. Say where you are.',
            'Puiku, supratau.' => 'Great, I understand.',
            'Gerai. Dabar žinau, kur esate.' => 'Good. Now I know where you are.',
            'Atsiprašau, ko ieškote?' => 'Excuse me, what are you looking for?',
            'Sveiki. Galiu padėti?' => 'Hello. Can I help?',
            'Tualetas yra ten.' => 'The toilet is there.',
            'Žinoma. Tualetas yra dešinėje.' => 'Of course. The toilet is on the right.',
            'Kur yra kasa: čia ar ten?' => 'Where is the cash desk: here or there?',
            'Ar vaistinė yra kairėje ar dešinėje?' => 'Is the pharmacy on the left or on the right?',
            'Taip, ten.' => 'Yes, there.',
            'Gerai. Dešinėje.' => 'Good. On the right.',
            'Laba diena. Jūs esate mieste.' => 'Good day. You are in town.',
            'Sveiki. Ko ieškote mieste?' => 'Hello. What are you looking for in town?',
            'Stotis yra ten, tiesiai.' => 'The station is there, straight ahead.',
            'Vaistinė yra čia, kairėje.' => 'The pharmacy is here, on the left.',
            'Eikite tiesiai.' => 'Go straight.',
            'Pasukite į kairę. Vaistinė yra ten.' => 'Turn left. The pharmacy is there.',
            'Puiku. Geros dienos!' => 'Great. Have a good day!',
            'Labai gerai. Iki!' => 'Very good. Bye!',
            'Laba diena. Ar jums reikia pagalbos?' => 'Good day. Do you need help?',
            'Sveiki. Ko ieškote?' => 'Hello. What are you looking for?',
            'Vaistinė yra ten, kairėje.' => 'The pharmacy is there, on the left.',
            'Žinoma. Eikite tiesiai.' => 'Of course. Go straight.',
            default => $line,
        };
    }
}
