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

class MaistasIrGerimaiUnitSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $language = Language::query()->where('code', 'lt')->firstOrFail();
            $character = Character::query()->where('slug', 'rasa')->firstOrFail();
            $unit = Unit::query()->updateOrCreate(
                ['slug' => 'maistas-ir-gerimai'],
                [
                    'language_id' => $language->id,
                    'title' => 'Maistas ir gėrimai',
                    'description' => 'A practical unit for ordering drinks, simple food, asking prices, and paying politely.',
                    'cefr_level' => 'a1',
                    'status' => ContentStatus::Published,
                    'sort_order' => 20,
                    'published_at' => now(),
                ],
            );

            $this->syncTranslations($unit, [
                'title' => ['en' => 'Food and Drinks', 'lt' => 'Maistas ir gėrimai'],
                'description' => [
                    'en' => 'A practical unit for ordering drinks, simple food, asking prices, and paying politely.',
                    'lt' => 'Praktiškas skyrius apie gėrimų ir paprasto maisto užsakymą, kainos klausimą ir mandagų mokėjimą.',
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
        $model = $scenario->note()->updateOrCreate(
            [],
            [
                'title' => $note['title'],
                'body' => $note['body'],
                'cefr_level' => 'a1',
                'estimated_minutes' => $note['estimated_minutes'] ?? 2,
                'status' => ContentStatus::Published,
            ],
        );

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

            $model->goals()
                ->whereNotIn('slug', collect($scene['goals'])->pluck('slug')->all())
                ->delete();
        }

        $goals = Goal::query()
            ->whereIn('scene_id', $scenes->pluck('id'))
            ->get()
            ->keyBy('slug');

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
                    'support_translation' => [
                        'en' => $line['support_translation'],
                        'lt' => $line['target_text'],
                    ],
                ]);
            }

            foreach ($scene['props'] ?? [] as $index => $prop) {
                $sceneProp = $model->props()->create([
                    'type' => $prop['type'] ?? 'menu_item',
                    'target_text' => $prop['target_text'],
                    'support_translation' => $prop['support_translation'],
                    'price' => $prop['price'] ?? '',
                    'metadata' => $prop['metadata'] ?? null,
                    'sort_order' => ($index + 1) * 10,
                ]);

                $this->syncTranslations($sceneProp, [
                    'support_translation' => [
                        'en' => $prop['support_translation'],
                        'lt' => $prop['target_text'],
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
            [
                'slug' => 'kavineje',
                'title' => 'Kavinėje',
                'subtitle' => 'Order one drink',
                'description' => 'Greet the barista and order coffee, tea, or water politely.',
                'emoji' => '☕',
                'tone' => 'amber',
                'is_free' => true,
                'sort_order' => 11,
                'start_scene' => 'uzsakymas',
                'translations' => [
                    'title' => ['en' => 'At the Cafe', 'lt' => 'Kavinėje'],
                    'subtitle' => ['en' => 'Order one drink', 'lt' => 'Užsisakykite vieną gėrimą'],
                    'description' => ['en' => 'Greet the barista and order coffee, tea, or water politely.', 'lt' => 'Pasisveikinkite su barista ir mandagiai užsisakykite kavos, arbatos arba vandens.'],
                ],
                'note' => [
                    'title' => 'Before you order a drink',
                    'body' => implode("\n\n", [
                        'This lesson gives you one very useful real-life sentence: Norėčiau ..., prašau.',
                        'Useful phrases:',
                        '- Norėčiau kavos, prašau. = I would like coffee, please.',
                        '- Norėčiau arbatos, prašau. = I would like tea, please.',
                        '- Vandens, prašau. = Water, please.',
                        '- Ačiū. = Thank you.',
                        'Grammar note: norėčiau is a polite fixed phrase. Learn it as “I would like.”',
                    ]),
                    'translations' => [
                        'title' => ['en' => 'Before you order a drink', 'lt' => 'Prieš užsisakant gėrimą'],
                        'body' => ['en' => implode("\n\n", [
                            'This lesson gives you one very useful real-life sentence: Norėčiau ..., prašau.',
                            'Useful phrases:',
                            '- Norėčiau kavos, prašau. = I would like coffee, please.',
                            '- Norėčiau arbatos, prašau. = I would like tea, please.',
                            '- Vandens, prašau. = Water, please.',
                            '- Ačiū. = Thank you.',
                            'Grammar note: norėčiau is a polite fixed phrase. Learn it as “I would like.”',
                        ]), 'lt' => implode("\n\n", [
                            'Ši pamoka duoda vieną labai praktišką sakinį: Norėčiau ..., prašau.',
                            'Naudingos frazės:',
                            '- Norėčiau kavos, prašau.',
                            '- Norėčiau arbatos, prašau.',
                            '- Vandens, prašau.',
                            '- Ačiū.',
                            'Gramatikos pastaba: norėčiau yra mandagi pastovi frazė. Mokykitės ją kaip „I would like“.',
                        ])],
                    ],
                ],
                'scenes' => [
                    [
                        'slug' => 'uzsakymas',
                        'title' => 'Order at the counter',
                        'setting' => 'Rasa is working at a small cafe counter.',
                        'translations' => [
                            'title' => ['en' => 'Order at the counter', 'lt' => 'Užsakymas prie prekystalio'],
                            'setting' => ['en' => 'Rasa is working at a small cafe counter.', 'lt' => 'Rasa dirba mažoje kavinėje prie prekystalio.'],
                        ],
                        'props' => [
                            ['target_text' => 'Kava', 'support_translation' => 'Coffee', 'price' => '2,50 €'],
                            ['target_text' => 'Arbata', 'support_translation' => 'Tea', 'price' => '2,00 €'],
                            ['target_text' => 'Vanduo', 'support_translation' => 'Water', 'price' => '1,50 €'],
                        ],
                        'lines' => [
                            ['target_text' => 'Laba diena! Ko norėsite?', 'support_translation' => 'Good day! What would you like?'],
                            ['target_text' => 'Sveiki. Ką užsakysite?', 'support_translation' => 'Hello. What will you order?'],
                            ['target_text' => 'Gerai, tuoj paruošiu.', 'support_translation' => 'Alright, I will prepare it now.', 'trigger_goal' => 'order-drink', 'priority' => 100],
                            ['target_text' => 'Žinoma. Vienas gėrimas.', 'support_translation' => 'Of course. One drink.', 'trigger_goal' => 'order-drink', 'priority' => 100],
                        ],
                        'goals' => [
                            [
                                'slug' => 'order-drink',
                                'label' => 'Order one drink',
                                'intent' => 'The learner greets and politely orders one simple drink, such as coffee, tea, or water.',
                                'example' => 'Laba diena. Norėčiau kavos, prašau.',
                                'next' => null,
                                'translations' => [
                                    'label' => ['en' => 'Order one drink', 'lt' => 'Užsisakykite vieną gėrimą'],
                                    'intent' => ['en' => 'The learner greets and politely orders one simple drink, such as coffee, tea, or water.', 'lt' => 'Mokinys pasisveikina ir mandagiai užsisako vieną paprastą gėrimą, pavyzdžiui, kavą, arbatą arba vandenį.'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'pienas-ar-cukrus',
                'title' => 'Pienas ar cukrus?',
                'subtitle' => 'Say with or without',
                'description' => 'Answer a simple preference question about milk and sugar.',
                'emoji' => '🥛',
                'tone' => 'mint',
                'sort_order' => 12,
                'start_scene' => 'pasirinkimas',
                'translations' => [
                    'title' => ['en' => 'Milk or Sugar?', 'lt' => 'Pienas ar cukrus?'],
                    'subtitle' => ['en' => 'Say with or without', 'lt' => 'Pasakykite su arba be'],
                    'description' => ['en' => 'Answer a simple preference question about milk and sugar.', 'lt' => 'Atsakykite į paprastą klausimą apie pieną ir cukrų.'],
                ],
                'note' => [
                    'title' => 'Before you answer about milk and sugar',
                    'body' => implode("\n\n", [
                        'Now you practise short preference answers.',
                        'Useful phrases:',
                        '- Su pienu, prašau. = With milk, please.',
                        '- Be pieno, prašau. = Without milk, please.',
                        '- Su cukrumi, prašau. = With sugar, please.',
                        '- Be cukraus, ačiū. = Without sugar, thank you.',
                        'Focus: su means with. Be means without.',
                    ]),
                    'translations' => [
                        'title' => ['en' => 'Before you answer about milk and sugar', 'lt' => 'Prieš atsakant apie pieną ir cukrų'],
                        'body' => ['en' => implode("\n\n", [
                            'Now you practise short preference answers.',
                            'Useful phrases:',
                            '- Su pienu, prašau. = With milk, please.',
                            '- Be pieno, prašau. = Without milk, please.',
                            '- Su cukrumi, prašau. = With sugar, please.',
                            '- Be cukraus, ačiū. = Without sugar, thank you.',
                            'Focus: su means with. Be means without.',
                        ]), 'lt' => implode("\n\n", [
                            'Dabar praktikuosite trumpus atsakymus apie pasirinkimą.',
                            'Naudingos frazės:',
                            '- Su pienu, prašau.',
                            '- Be pieno, prašau.',
                            '- Su cukrumi, prašau.',
                            '- Be cukraus, ačiū.',
                            'Svarbu: su reiškia „with“. Be reiškia „without“.',
                        ])],
                    ],
                ],
                'scenes' => [
                    [
                        'slug' => 'pasirinkimas',
                        'title' => 'Choose additions',
                        'setting' => 'Rasa is preparing your drink and asks what you want in it.',
                        'translations' => [
                            'title' => ['en' => 'Choose additions', 'lt' => 'Pasirinkite priedus'],
                            'setting' => ['en' => 'Rasa is preparing your drink and asks what you want in it.', 'lt' => 'Rasa ruošia jūsų gėrimą ir klausia, ko norite į jį.'],
                        ],
                        'lines' => [
                            ['target_text' => 'Ar norėsite pieno arba cukraus?', 'support_translation' => 'Would you like milk or sugar?'],
                            ['target_text' => 'Su pienu ar be pieno?', 'support_translation' => 'With milk or without milk?'],
                            ['target_text' => 'Gerai, supratau.', 'support_translation' => 'Alright, I understand.', 'trigger_goal' => 'answer-additions', 'priority' => 100],
                            ['target_text' => 'Puiku. Tuoj paruošiu.', 'support_translation' => 'Great. I will prepare it now.', 'trigger_goal' => 'answer-additions', 'priority' => 100],
                        ],
                        'goals' => [
                            [
                                'slug' => 'answer-additions',
                                'label' => 'Say with or without',
                                'intent' => 'The learner answers whether they want milk or sugar using su or be.',
                                'example' => 'Su pienu, prašau.',
                                'next' => null,
                                'translations' => [
                                    'label' => ['en' => 'Say with or without', 'lt' => 'Pasakykite su arba be'],
                                    'intent' => ['en' => 'The learner answers whether they want milk or sugar using su or be.', 'lt' => 'Mokinys atsako, ar nori pieno arba cukraus, vartodamas su arba be.'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'kiek-kainuoja',
                'title' => 'Kiek kainuoja?',
                'subtitle' => 'Ask the price',
                'description' => 'Ask how much a drink or snack costs.',
                'emoji' => '💶',
                'tone' => 'sky',
                'sort_order' => 13,
                'start_scene' => 'kaina',
                'translations' => [
                    'title' => ['en' => 'How Much Is It?', 'lt' => 'Kiek kainuoja?'],
                    'subtitle' => ['en' => 'Ask the price', 'lt' => 'Paklauskite kainos'],
                    'description' => ['en' => 'Ask how much a drink or snack costs.', 'lt' => 'Paklauskite, kiek kainuoja gėrimas arba užkandis.'],
                ],
                'note' => [
                    'title' => 'Before you ask the price',
                    'body' => implode("\n\n", [
                        'This lesson practises one question you will use often.',
                        'Useful phrases:',
                        '- Kiek kainuoja kava? = How much does coffee cost?',
                        '- Kiek tai kainuoja? = How much does this cost?',
                        '- Du eurai. = Two euros.',
                        '- Ačiū. = Thank you.',
                        'Question word: kiek means how much or how many.',
                    ]),
                    'translations' => [
                        'title' => ['en' => 'Before you ask the price', 'lt' => 'Prieš klausiant kainos'],
                        'body' => ['en' => implode("\n\n", [
                            'This lesson practises one question you will use often.',
                            'Useful phrases:',
                            '- Kiek kainuoja kava? = How much does coffee cost?',
                            '- Kiek tai kainuoja? = How much does this cost?',
                            '- Du eurai. = Two euros.',
                            '- Ačiū. = Thank you.',
                            'Question word: kiek means how much or how many.',
                        ]), 'lt' => implode("\n\n", [
                            'Šioje pamokoje praktikuosite klausimą, kurį dažnai vartosite.',
                            'Naudingos frazės:',
                            '- Kiek kainuoja kava?',
                            '- Kiek tai kainuoja?',
                            '- Du eurai.',
                            '- Ačiū.',
                            'Klausiamasis žodis: kiek reiškia „how much“ arba „how many“.',
                        ])],
                    ],
                ],
                'scenes' => [
                    [
                        'slug' => 'kaina',
                        'title' => 'Ask at the counter',
                        'setting' => 'There is a short menu on the counter.',
                        'translations' => [
                            'title' => ['en' => 'Ask at the counter', 'lt' => 'Paklauskite prie prekystalio'],
                            'setting' => ['en' => 'There is a short menu on the counter.', 'lt' => 'Ant prekystalio yra trumpas meniu.'],
                        ],
                        'props' => [
                            ['target_text' => 'Kava', 'support_translation' => 'Coffee', 'price' => '2,50 €'],
                            ['target_text' => 'Arbata', 'support_translation' => 'Tea', 'price' => '2,00 €'],
                            ['target_text' => 'Sumuštinis', 'support_translation' => 'Sandwich', 'price' => '4,00 €'],
                        ],
                        'lines' => [
                            ['target_text' => 'Prašom, galite klausti.', 'support_translation' => 'Please, you can ask.'],
                            ['target_text' => 'Ko ieškote?', 'support_translation' => 'What are you looking for?'],
                            ['target_text' => 'Kava kainuoja du eurus penkiasdešimt.', 'support_translation' => 'Coffee costs two euros fifty.', 'trigger_goal' => 'ask-price', 'priority' => 100],
                            ['target_text' => 'Tai kainuoja keturis eurus.', 'support_translation' => 'That costs four euros.', 'trigger_goal' => 'ask-price', 'priority' => 100],
                        ],
                        'goals' => [
                            [
                                'slug' => 'ask-price',
                                'label' => 'Ask how much it costs',
                                'intent' => 'The learner asks how much a drink, snack, or item costs.',
                                'example' => 'Kiek kainuoja kava?',
                                'next' => null,
                                'translations' => [
                                    'label' => ['en' => 'Ask how much it costs', 'lt' => 'Paklauskite, kiek kainuoja'],
                                    'intent' => ['en' => 'The learner asks how much a drink, snack, or item costs.', 'lt' => 'Mokinys paklausia, kiek kainuoja gėrimas, užkandis arba daiktas.'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'mokame-uz-gerima',
                'title' => 'Mokame už gėrimą',
                'subtitle' => 'Pay by card or cash',
                'description' => 'Say how you will pay and end politely.',
                'emoji' => '💳',
                'tone' => 'berry',
                'sort_order' => 14,
                'start_scene' => 'mokejimas',
                'translations' => [
                    'title' => ['en' => 'Paying for a Drink', 'lt' => 'Mokame už gėrimą'],
                    'subtitle' => ['en' => 'Pay by card or cash', 'lt' => 'Mokėkite kortele arba grynaisiais'],
                    'description' => ['en' => 'Say how you will pay and end politely.', 'lt' => 'Pasakykite, kaip mokėsite, ir mandagiai užbaikite pokalbį.'],
                ],
                'note' => [
                    'title' => 'Before you pay',
                    'body' => implode("\n\n", [
                        'Payment phrases are short and useful.',
                        'Useful phrases:',
                        '- Mokėsiu kortele. = I will pay by card.',
                        '- Mokėsiu grynaisiais. = I will pay in cash.',
                        '- Ačiū. = Thank you.',
                        '- Viso gero. = Goodbye.',
                        'A short answer is enough.',
                    ]),
                    'translations' => [
                        'title' => ['en' => 'Before you pay', 'lt' => 'Prieš mokant'],
                        'body' => ['en' => implode("\n\n", [
                            'Payment phrases are short and useful.',
                            'Useful phrases:',
                            '- Mokėsiu kortele. = I will pay by card.',
                            '- Mokėsiu grynaisiais. = I will pay in cash.',
                            '- Ačiū. = Thank you.',
                            '- Viso gero. = Goodbye.',
                            'A short answer is enough.',
                        ]), 'lt' => implode("\n\n", [
                            'Mokėjimo frazės yra trumpos ir naudingos.',
                            'Naudingos frazės:',
                            '- Mokėsiu kortele.',
                            '- Mokėsiu grynaisiais.',
                            '- Ačiū.',
                            '- Viso gero.',
                            'Trumpo atsakymo pakanka.',
                        ])],
                    ],
                ],
                'scenes' => [
                    [
                        'slug' => 'mokejimas',
                        'title' => 'Pay and leave',
                        'setting' => 'Your drink is ready and Rasa tells you the price.',
                        'translations' => [
                            'title' => ['en' => 'Pay and leave', 'lt' => 'Sumokėkite ir išeikite'],
                            'setting' => ['en' => 'Your drink is ready and Rasa tells you the price.', 'lt' => 'Jūsų gėrimas paruoštas, o Rasa pasako kainą.'],
                        ],
                        'lines' => [
                            ['target_text' => 'Jūsų gėrimas paruoštas. Bus du eurai.', 'support_translation' => 'Your drink is ready. That will be two euros.'],
                            ['target_text' => 'Kaip mokėsite?', 'support_translation' => 'How will you pay?'],
                            ['target_text' => 'Puiku. Kortele apmokėta.', 'support_translation' => 'Great. Paid by card.', 'trigger_goal' => 'pay-card', 'priority' => 100],
                            ['target_text' => 'Gerai. Ačiū, gavau grynuosius.', 'support_translation' => 'Alright. Thank you, I received the cash.', 'trigger_goal' => 'pay-cash', 'priority' => 100],
                            ['target_text' => 'Ačiū jums. Geros dienos!', 'support_translation' => 'Thank you. Have a nice day!', 'trigger_goal' => 'thank-goodbye', 'priority' => 100],
                        ],
                        'goals' => [
                            [
                                'slug' => 'pay-card',
                                'label' => 'Say you will pay by card',
                                'intent' => 'The learner says they will pay by card.',
                                'example' => 'Mokėsiu kortele.',
                                'next' => null,
                                'translations' => [
                                    'label' => ['en' => 'Say you will pay by card', 'lt' => 'Pasakykite, kad mokėsite kortele'],
                                    'intent' => ['en' => 'The learner says they will pay by card.', 'lt' => 'Mokinys pasako, kad mokės kortele.'],
                                ],
                            ],
                            [
                                'slug' => 'pay-cash',
                                'label' => 'Say you will pay in cash',
                                'intent' => 'The learner says they will pay in cash.',
                                'example' => 'Mokėsiu grynaisiais.',
                                'next' => null,
                                'translations' => [
                                    'label' => ['en' => 'Say you will pay in cash', 'lt' => 'Pasakykite, kad mokėsite grynaisiais'],
                                    'intent' => ['en' => 'The learner says they will pay in cash.', 'lt' => 'Mokinys pasako, kad mokės grynaisiais.'],
                                ],
                            ],
                            [
                                'slug' => 'thank-goodbye',
                                'label' => 'Thank and say goodbye',
                                'intent' => 'The learner thanks the worker and says goodbye.',
                                'example' => 'Ačiū, viso gero.',
                                'next' => null,
                                'translations' => [
                                    'label' => ['en' => 'Thank and say goodbye', 'lt' => 'Padėkokite ir atsisveikinkite'],
                                    'intent' => ['en' => 'The learner thanks the worker and says goodbye.', 'lt' => 'Mokinys padėkoja darbuotojai ir atsisveikina.'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'uzkandis-ir-gerimas',
                'title' => 'Užkandis ir gėrimas',
                'subtitle' => 'Order two simple things',
                'description' => 'Combine a drink and a snack in one short order.',
                'emoji' => '🥪',
                'tone' => 'mint',
                'sort_order' => 15,
                'start_scene' => 'du-dalykai',
                'translations' => [
                    'title' => ['en' => 'A Snack and a Drink', 'lt' => 'Užkandis ir gėrimas'],
                    'subtitle' => ['en' => 'Order two simple things', 'lt' => 'Užsisakykite du paprastus dalykus'],
                    'description' => ['en' => 'Combine a drink and a snack in one short order.', 'lt' => 'Vienu trumpu užsakymu paprašykite gėrimo ir užkandžio.'],
                ],
                'note' => [
                    'title' => 'Before you order food and a drink',
                    'body' => implode("\n\n", [
                        'Now you combine two useful words in one order.',
                        'Useful phrases:',
                        '- Norėčiau kavos ir sumuštinio, prašau. = I would like coffee and a sandwich, please.',
                        '- Norėčiau arbatos ir pyrago. = I would like tea and cake.',
                        '- Ir = and.',
                        '- Dar vandens, prašau. = Also water, please.',
                        'Focus: ir links two simple things.',
                    ]),
                    'translations' => [
                        'title' => ['en' => 'Before you order food and a drink', 'lt' => 'Prieš užsisakant maistą ir gėrimą'],
                        'body' => ['en' => implode("\n\n", [
                            'Now you combine two useful words in one order.',
                            'Useful phrases:',
                            '- Norėčiau kavos ir sumuštinio, prašau. = I would like coffee and a sandwich, please.',
                            '- Norėčiau arbatos ir pyrago. = I would like tea and cake.',
                            '- Ir = and.',
                            '- Dar vandens, prašau. = Also water, please.',
                            'Focus: ir links two simple things.',
                        ]), 'lt' => implode("\n\n", [
                            'Dabar viename užsakyme sujungsite du naudingus žodžius.',
                            'Naudingos frazės:',
                            '- Norėčiau kavos ir sumuštinio, prašau.',
                            '- Norėčiau arbatos ir pyrago.',
                            '- Ir = „and“.',
                            '- Dar vandens, prašau.',
                            'Svarbu: ir sujungia du paprastus dalykus.',
                        ])],
                    ],
                ],
                'scenes' => [
                    [
                        'slug' => 'du-dalykai',
                        'title' => 'Order two items',
                        'setting' => 'Rasa points to the drinks and snacks on the counter.',
                        'translations' => [
                            'title' => ['en' => 'Order two items', 'lt' => 'Užsisakykite du dalykus'],
                            'setting' => ['en' => 'Rasa points to the drinks and snacks on the counter.', 'lt' => 'Rasa parodo gėrimus ir užkandžius ant prekystalio.'],
                        ],
                        'props' => [
                            ['target_text' => 'Kava', 'support_translation' => 'Coffee', 'price' => '2,50 €'],
                            ['target_text' => 'Arbata', 'support_translation' => 'Tea', 'price' => '2,00 €'],
                            ['target_text' => 'Sumuštinis', 'support_translation' => 'Sandwich', 'price' => '4,00 €'],
                            ['target_text' => 'Pyragas', 'support_translation' => 'Cake', 'price' => '3,00 €'],
                        ],
                        'lines' => [
                            ['target_text' => 'Ko norėsite šiandien?', 'support_translation' => 'What would you like today?'],
                            ['target_text' => 'Turime kavos, arbatos, sumuštinių ir pyrago.', 'support_translation' => 'We have coffee, tea, sandwiches, and cake.'],
                            ['target_text' => 'Puiku. Vienas užkandis ir vienas gėrimas.', 'support_translation' => 'Great. One snack and one drink.', 'trigger_goal' => 'order-two-items', 'priority' => 100],
                            ['target_text' => 'Gerai, tuoj paruošiu.', 'support_translation' => 'Alright, I will prepare it now.', 'trigger_goal' => 'order-two-items', 'priority' => 100],
                        ],
                        'goals' => [
                            [
                                'slug' => 'order-two-items',
                                'label' => 'Order a snack and a drink',
                                'intent' => 'The learner orders one drink and one simple food item in the same sentence.',
                                'example' => 'Norėčiau kavos ir sumuštinio, prašau.',
                                'next' => null,
                                'translations' => [
                                    'label' => ['en' => 'Order a snack and a drink', 'lt' => 'Užsisakykite užkandį ir gėrimą'],
                                    'intent' => ['en' => 'The learner orders one drink and one simple food item in the same sentence.', 'lt' => 'Mokinys vienu sakiniu užsisako vieną gėrimą ir vieną paprastą maisto produktą.'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'restoranas',
                'title' => 'Restorane',
                'subtitle' => 'Put the food phrases together',
                'description' => 'Ask for a table, order simple food and drink, then pay.',
                'emoji' => '🍽️',
                'tone' => 'primary',
                'sort_order' => 16,
                'start_scene' => 'atvykimas',
                'translations' => [
                    'title' => ['en' => 'At the Restaurant', 'lt' => 'Restorane'],
                    'subtitle' => ['en' => 'Put the food phrases together', 'lt' => 'Sujunkite maisto frazes'],
                    'description' => ['en' => 'Ask for a table, order simple food and drink, then pay.', 'lt' => 'Paprašykite staliuko, užsisakykite paprasto maisto ir gėrimo, tada sumokėkite.'],
                ],
                'note' => [
                    'title' => 'Before the restaurant capstone',
                    'body' => implode("\n\n", [
                        'This scenario combines the useful phrases from the food and drinks unit.',
                        'Useful phrases:',
                        '- Norėčiau staliuko, prašau. = I would like a table, please.',
                        '- Ar galiu gauti meniu? = Can I get the menu?',
                        '- Norėčiau sriubos ir vandens, prašau. = I would like soup and water, please.',
                        '- Mokėsiu kortele. = I will pay by card.',
                        'Goal: keep the conversation polite and simple.',
                    ]),
                    'translations' => [
                        'title' => ['en' => 'Before the restaurant capstone', 'lt' => 'Prieš restorano pakartojimą'],
                        'body' => ['en' => implode("\n\n", [
                            'This scenario combines the useful phrases from the food and drinks unit.',
                            'Useful phrases:',
                            '- Norėčiau staliuko, prašau. = I would like a table, please.',
                            '- Ar galiu gauti meniu? = Can I get the menu?',
                            '- Norėčiau sriubos ir vandens, prašau. = I would like soup and water, please.',
                            '- Mokėsiu kortele. = I will pay by card.',
                            'Goal: keep the conversation polite and simple.',
                        ]), 'lt' => implode("\n\n", [
                            'Šis scenarijus sujungia naudingas maisto ir gėrimų skyriaus frazes.',
                            'Naudingos frazės:',
                            '- Norėčiau staliuko, prašau.',
                            '- Ar galiu gauti meniu?',
                            '- Norėčiau sriubos ir vandens, prašau.',
                            '- Mokėsiu kortele.',
                            'Tikslas: kalbėkite mandagiai ir paprastai.',
                        ])],
                    ],
                ],
                'scenes' => [
                    [
                        'slug' => 'atvykimas',
                        'title' => 'Ask for a table',
                        'setting' => 'Rasa greets the learner at a small restaurant.',
                        'translations' => [
                            'title' => ['en' => 'Ask for a table', 'lt' => 'Paprašykite staliuko'],
                            'setting' => ['en' => 'Rasa greets the learner at a small restaurant.', 'lt' => 'Rasa pasitinka mokinį mažame restorane.'],
                        ],
                        'lines' => [
                            ['target_text' => 'Laba diena! Ar norite staliuko?', 'support_translation' => 'Good day! Would you like a table?'],
                            ['target_text' => 'Sveiki atvykę. Kuo galiu padėti?', 'support_translation' => 'Welcome. How can I help?'],
                            ['target_text' => 'Žinoma. Prašom sėstis.', 'support_translation' => 'Of course. Please sit down.', 'trigger_goal' => 'ask-table', 'priority' => 100],
                        ],
                        'goals' => [
                            [
                                'slug' => 'ask-table',
                                'label' => 'Ask for a table',
                                'intent' => 'The learner greets and asks for a table politely.',
                                'example' => 'Laba diena. Norėčiau staliuko, prašau.',
                                'next' => 'meniu',
                                'translations' => [
                                    'label' => ['en' => 'Ask for a table', 'lt' => 'Paprašykite staliuko'],
                                    'intent' => ['en' => 'The learner greets and asks for a table politely.', 'lt' => 'Mokinys pasisveikina ir mandagiai paprašo staliuko.'],
                                ],
                            ],
                        ],
                    ],
                    [
                        'slug' => 'meniu',
                        'title' => 'Ask for the menu',
                        'setting' => 'The learner is seated and needs the menu.',
                        'translations' => [
                            'title' => ['en' => 'Ask for the menu', 'lt' => 'Paprašykite meniu'],
                            'setting' => ['en' => 'The learner is seated and needs the menu.', 'lt' => 'Mokinys sėdi prie staliuko ir nori meniu.'],
                        ],
                        'props' => [
                            ['target_text' => 'Sriuba', 'support_translation' => 'Soup', 'price' => '4,00 €'],
                            ['target_text' => 'Salotos', 'support_translation' => 'Salad', 'price' => '5,00 €'],
                            ['target_text' => 'Vanduo', 'support_translation' => 'Water', 'price' => '1,50 €'],
                            ['target_text' => 'Arbata', 'support_translation' => 'Tea', 'price' => '2,00 €'],
                        ],
                        'lines' => [
                            ['target_text' => 'Prašom. Ko norėsite?', 'support_translation' => 'Here you are. What would you like?'],
                            ['target_text' => 'Ar norėtumėte meniu?', 'support_translation' => 'Would you like the menu?'],
                            ['target_text' => 'Taip, štai meniu.', 'support_translation' => 'Yes, here is the menu.', 'trigger_goal' => 'ask-menu', 'priority' => 100],
                        ],
                        'goals' => [
                            [
                                'slug' => 'ask-menu',
                                'label' => 'Ask for the menu',
                                'intent' => 'The learner asks for the menu politely.',
                                'example' => 'Ar galiu gauti meniu?',
                                'next' => 'uzsakymas',
                                'translations' => [
                                    'label' => ['en' => 'Ask for the menu', 'lt' => 'Paprašykite meniu'],
                                    'intent' => ['en' => 'The learner asks for the menu politely.', 'lt' => 'Mokinys mandagiai paprašo meniu.'],
                                ],
                            ],
                        ],
                    ],
                    [
                        'slug' => 'uzsakymas',
                        'title' => 'Order food and drink',
                        'setting' => 'Rasa returns to take the order.',
                        'translations' => [
                            'title' => ['en' => 'Order food and drink', 'lt' => 'Užsisakykite maistą ir gėrimą'],
                            'setting' => ['en' => 'Rasa returns to take the order.', 'lt' => 'Rasa grįžta priimti užsakymo.'],
                        ],
                        'lines' => [
                            ['target_text' => 'Ką užsakysite?', 'support_translation' => 'What will you order?'],
                            ['target_text' => 'Ko norėsite valgyti ir gerti?', 'support_translation' => 'What would you like to eat and drink?'],
                            ['target_text' => 'Puiku. Sriuba ir vanduo.', 'support_translation' => 'Great. Soup and water.', 'trigger_goal' => 'order-food-drink', 'priority' => 100],
                            ['target_text' => 'Gerai, tuoj atnešiu.', 'support_translation' => 'Alright, I will bring it soon.', 'trigger_goal' => 'order-food-drink', 'priority' => 100],
                        ],
                        'goals' => [
                            [
                                'slug' => 'order-food-drink',
                                'label' => 'Order food and a drink',
                                'intent' => 'The learner orders one simple food item and one drink politely.',
                                'example' => 'Norėčiau sriubos ir vandens, prašau.',
                                'next' => 'mokejimas',
                                'translations' => [
                                    'label' => ['en' => 'Order food and a drink', 'lt' => 'Užsisakykite maistą ir gėrimą'],
                                    'intent' => ['en' => 'The learner orders one simple food item and one drink politely.', 'lt' => 'Mokinys mandagiai užsisako vieną paprastą maisto patiekalą ir vieną gėrimą.'],
                                ],
                            ],
                        ],
                    ],
                    [
                        'slug' => 'mokejimas',
                        'title' => 'Pay at the end',
                        'setting' => 'The meal is finished and Rasa brings the bill.',
                        'translations' => [
                            'title' => ['en' => 'Pay at the end', 'lt' => 'Sumokėkite pabaigoje'],
                            'setting' => ['en' => 'The meal is finished and Rasa brings the bill.', 'lt' => 'Valgis baigtas, ir Rasa atneša sąskaitą.'],
                        ],
                        'lines' => [
                            ['target_text' => 'Štai sąskaita. Kaip mokėsite?', 'support_translation' => 'Here is the bill. How will you pay?'],
                            ['target_text' => 'Viskas kainuoja penkis eurus penkiasdešimt.', 'support_translation' => 'Everything costs five euros fifty.'],
                            ['target_text' => 'Ačiū. Kortele apmokėta.', 'support_translation' => 'Thank you. Paid by card.', 'trigger_goal' => 'restaurant-pay-card', 'priority' => 100],
                            ['target_text' => 'Ačiū jums. Viso gero!', 'support_translation' => 'Thank you. Goodbye!', 'trigger_goal' => 'restaurant-goodbye', 'priority' => 100],
                        ],
                        'goals' => [
                            [
                                'slug' => 'restaurant-pay-card',
                                'label' => 'Pay by card',
                                'intent' => 'The learner says they will pay by card.',
                                'example' => 'Mokėsiu kortele.',
                                'next' => null,
                                'translations' => [
                                    'label' => ['en' => 'Pay by card', 'lt' => 'Mokėkite kortele'],
                                    'intent' => ['en' => 'The learner says they will pay by card.', 'lt' => 'Mokinys pasako, kad mokės kortele.'],
                                ],
                            ],
                            [
                                'slug' => 'restaurant-goodbye',
                                'label' => 'Thank and say goodbye',
                                'intent' => 'The learner thanks the waitress and says goodbye.',
                                'example' => 'Ačiū, viso gero.',
                                'next' => null,
                                'translations' => [
                                    'label' => ['en' => 'Thank and say goodbye', 'lt' => 'Padėkokite ir atsisveikinkite'],
                                    'intent' => ['en' => 'The learner thanks the waitress and says goodbye.', 'lt' => 'Mokinys padėkoja padavėjai ir atsisveikina.'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
