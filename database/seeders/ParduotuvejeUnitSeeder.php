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

class ParduotuvejeUnitSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $language = Language::query()->where('code', 'lt')->firstOrFail();
            $character = Character::query()->where('slug', 'rasa')->firstOrFail();
            $unit = Unit::query()->updateOrCreate(
                ['slug' => 'parduotuveje'],
                [
                    'language_id' => $language->id,
                    'title' => 'Parduotuvėje',
                    'description' => 'A practical unit for asking for items, saying what you need, choosing quantities, asking prices, and paying.',
                    'cefr_level' => 'a1',
                    'status' => ContentStatus::Published,
                    'sort_order' => 60,
                    'published_at' => now(),
                ],
            );

            $this->syncTranslations($unit, [
                'title' => ['en' => 'At the Shop', 'lt' => 'Parduotuvėje'],
                'description' => [
                    'en' => 'A practical unit for asking for items, saying what you need, choosing quantities, asking prices, and paying.',
                    'lt' => 'Praktiškas skyrius apie prekių prašymą, poreikių išsakymą, kiekį, kainą ir mokėjimą.',
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
                'slug' => 'duonos-ir-pieno',
                'title' => 'Duonos ir pieno',
                'title_en' => 'Bread and Milk',
                'subtitle' => 'Ask for one item',
                'subtitle_lt' => 'Paprašykite vienos prekės',
                'description' => 'Ask for bread, milk, water, or another simple item.',
                'description_lt' => 'Paprašykite duonos, pieno, vandens arba kitos paprastos prekės.',
                'emoji' => '🍞',
                'tone' => 'primary',
                'sort_order' => 51,
                'scene_slug' => 'preke',
                'goal_slug' => 'ask-for-item',
                'goal_label' => 'Ask for an item',
                'goal_label_lt' => 'Paprašykite prekės',
                'goal_intent' => 'The learner politely asks for one simple shop item.',
                'goal_intent_lt' => 'Mokinys mandagiai paprašo vienos paprastos parduotuvės prekės.',
                'example' => 'Norėčiau duonos, prašau.',
                'openings' => ['Laba diena. Ko ieškote?', 'Sveiki. Ko norėsite?'],
                'replies' => ['Žinoma, štai duona.', 'Gerai, prašom.'],
                'note' => [
                    'en' => [
                        'This lesson starts shopping with one useful request.',
                        'Useful phrases:',
                        '- Norėčiau duonos, prašau. = I would like bread, please.',
                        '- Norėčiau pieno, prašau. = I would like milk, please.',
                        '- Vandens, prašau. = Water, please.',
                        '- Ačiū. = Thank you.',
                        'Goal: ask for one item politely.',
                    ],
                    'lt' => [
                        'Ši pamoka pradeda apsipirkimą viena naudinga fraze.',
                        'Naudingos frazės:',
                        '- Norėčiau duonos, prašau.',
                        '- Norėčiau pieno, prašau.',
                        '- Vandens, prašau.',
                        '- Ačiū.',
                        'Tikslas: mandagiai paprašykite vienos prekės.',
                    ],
                ],
            ]),
            $this->scenario([
                'slug' => 'man-reikia',
                'title' => 'Man reikia',
                'title_en' => 'I Need',
                'subtitle' => 'Say what you need',
                'subtitle_lt' => 'Pasakykite, ko jums reikia',
                'description' => 'Use man reikia with simple everyday items.',
                'description_lt' => 'Vartokite man reikia su paprastomis kasdienėmis prekėmis.',
                'emoji' => '🧺',
                'tone' => 'mint',
                'sort_order' => 52,
                'scene_slug' => 'poreikis',
                'goal_slug' => 'say-need',
                'goal_label' => 'Say what you need',
                'goal_label_lt' => 'Pasakykite, ko reikia',
                'goal_intent' => 'The learner says what they need using man reikia.',
                'goal_intent_lt' => 'Mokinys pasako, ko jam arba jai reikia, vartodamas man reikia.',
                'example' => 'Man reikia vandens.',
                'openings' => ['Ko jums reikia?', 'Pasakykite, ko jums reikia.'],
                'replies' => ['Supratau. Tuoj pažiūrėsiu.', 'Gerai. Padėsiu jums.'],
                'note' => [
                    'en' => [
                        'This lesson gives you a very useful shopping pattern.',
                        'Useful phrases:',
                        '- Man reikia duonos. = I need bread.',
                        '- Man reikia pieno. = I need milk.',
                        '- Man reikia vandens. = I need water.',
                        '- Man reikia maišelio. = I need a bag.',
                        'Tiny grammar: learn Man reikia ... as one fixed speaking pattern.',
                    ],
                    'lt' => [
                        'Ši pamoka duoda labai naudingą apsipirkimo modelį.',
                        'Naudingos frazės:',
                        '- Man reikia duonos.',
                        '- Man reikia pieno.',
                        '- Man reikia vandens.',
                        '- Man reikia maišelio.',
                        'Maža gramatikos pastaba: mokykitės Man reikia ... kaip vieną pastovią frazę.',
                    ],
                ],
            ]),
            $this->scenario([
                'slug' => 'vienas-ar-du',
                'title' => 'Vienas ar du?',
                'title_en' => 'One or Two?',
                'subtitle' => 'Choose a quantity',
                'subtitle_lt' => 'Pasirinkite kiekį',
                'description' => 'Say one, two, or a small quantity when shopping.',
                'description_lt' => 'Apsipirkdami pasakykite vieną, du arba mažą kiekį.',
                'emoji' => '2️⃣',
                'tone' => 'sky',
                'sort_order' => 53,
                'scene_slug' => 'kiekis',
                'goal_slug' => 'say-quantity',
                'goal_label' => 'Say the quantity',
                'goal_label_lt' => 'Pasakykite kiekį',
                'goal_intent' => 'The learner says a small quantity for a shop item.',
                'goal_intent_lt' => 'Mokinys pasako mažą prekės kiekį.',
                'example' => 'Du, prašau.',
                'openings' => ['Kiek norėsite?', 'Vieną ar du?'],
                'replies' => ['Gerai, du.', 'Puiku. Kiekį supratau.'],
                'note' => [
                    'en' => [
                        'This lesson practises small quantities.',
                        'Useful phrases:',
                        '- Vieną, prašau. = One, please.',
                        '- Du, prašau. = Two, please.',
                        '- Vieną duoną, prašau. = One bread, please.',
                        '- Du obuolius, prašau. = Two apples, please.',
                        'Tip: short answers are natural when the shop worker asks how many.',
                    ],
                    'lt' => [
                        'Ši pamoka praktikuoja mažus kiekius.',
                        'Naudingos frazės:',
                        '- Vieną, prašau.',
                        '- Du, prašau.',
                        '- Vieną duoną, prašau.',
                        '- Du obuolius, prašau.',
                        'Patarimas: trumpi atsakymai natūralūs, kai pardavėja klausia kiekio.',
                    ],
                ],
            ]),
            $this->scenario([
                'slug' => 'kaina-parduotuveje',
                'title' => 'Kaina parduotuvėje',
                'title_en' => 'Price at the Shop',
                'subtitle' => 'Ask how much it costs',
                'subtitle_lt' => 'Paklauskite, kiek kainuoja',
                'description' => 'Ask the price of one item in a shop.',
                'description_lt' => 'Paklauskite vienos prekės kainos parduotuvėje.',
                'emoji' => '💶',
                'tone' => 'amber',
                'sort_order' => 54,
                'scene_slug' => 'kaina',
                'goal_slug' => 'ask-shop-price',
                'goal_label' => 'Ask the price',
                'goal_label_lt' => 'Paklauskite kainos',
                'goal_intent' => 'The learner asks how much a shop item costs.',
                'goal_intent_lt' => 'Mokinys paklausia, kiek kainuoja parduotuvės prekė.',
                'example' => 'Kiek kainuoja pienas?',
                'openings' => ['Galite paklausti kainos.', 'Kokios prekės kainos norite paklausti?'],
                'replies' => ['Pienas kainuoja vieną eurą.', 'Tai kainuoja du eurus.'],
                'note' => [
                    'en' => [
                        'This lesson repeats the price question in a shop context.',
                        'Useful phrases:',
                        '- Kiek kainuoja pienas? = How much does milk cost?',
                        '- Kiek kainuoja duona? = How much does bread cost?',
                        '- Kiek tai kainuoja? = How much does this cost?',
                        '- Ačiū. = Thank you.',
                        'Question word: kiek means how much or how many.',
                    ],
                    'lt' => [
                        'Ši pamoka pakartoja kainos klausimą parduotuvėje.',
                        'Naudingos frazės:',
                        '- Kiek kainuoja pienas?',
                        '- Kiek kainuoja duona?',
                        '- Kiek tai kainuoja?',
                        '- Ačiū.',
                        'Klausiamasis žodis: kiek reiškia „how much“ arba „how many“.',
                    ],
                ],
            ]),
            $this->scenario([
                'slug' => 'prie-kasos',
                'title' => 'Prie kasos',
                'title_en' => 'At the Checkout',
                'subtitle' => 'Pay for shopping',
                'subtitle_lt' => 'Sumokėkite už pirkinius',
                'description' => 'Say how you will pay and answer a simple checkout question.',
                'description_lt' => 'Pasakykite, kaip mokėsite, ir atsakykite į paprastą klausimą prie kasos.',
                'emoji' => '🧾',
                'tone' => 'berry',
                'sort_order' => 55,
                'scene_slug' => 'kasa',
                'goal_slug' => 'pay-at-checkout',
                'goal_label' => 'Say how you will pay',
                'goal_label_lt' => 'Pasakykite, kaip mokėsite',
                'goal_intent' => 'The learner says they will pay by card or cash at checkout.',
                'goal_intent_lt' => 'Mokinys prie kasos pasako, kad mokės kortele arba grynaisiais.',
                'example' => 'Mokėsiu kortele.',
                'openings' => ['Bus trys eurai. Kaip mokėsite?', 'Ar mokėsite kortele?'],
                'replies' => ['Gerai. Kortele apmokėta.', 'Ačiū. Čekis čia.'],
                'note' => [
                    'en' => [
                        'This lesson practises checkout phrases.',
                        'Useful phrases:',
                        '- Mokėsiu kortele. = I will pay by card.',
                        '- Mokėsiu grynaisiais. = I will pay in cash.',
                        '- Ar reikia maišelio? = Do you need a bag?',
                        '- Ne, ačiū. = No, thank you.',
                        'Goal: answer clearly at the checkout.',
                    ],
                    'lt' => [
                        'Ši pamoka praktikuoja frazes prie kasos.',
                        'Naudingos frazės:',
                        '- Mokėsiu kortele.',
                        '- Mokėsiu grynaisiais.',
                        '- Ar reikia maišelio?',
                        '- Ne, ačiū.',
                        'Tikslas: aiškiai atsakykite prie kasos.',
                    ],
                ],
            ]),
            [
                'slug' => 'apsipirkimas-parduotuveje',
                'title' => 'Apsipirkimas parduotuvėje',
                'subtitle' => 'Put the shop phrases together',
                'description' => 'Ask for items, choose a quantity, ask the price, and pay politely.',
                'emoji' => '🛒',
                'tone' => 'primary',
                'sort_order' => 56,
                'start_scene' => 'prekiu-prasymas',
                'translations' => [
                    'title' => ['en' => 'Shopping at the Store', 'lt' => 'Apsipirkimas parduotuvėje'],
                    'subtitle' => ['en' => 'Put the shop phrases together', 'lt' => 'Sujunkite parduotuvės frazes'],
                    'description' => ['en' => 'Ask for items, choose a quantity, ask the price, and pay politely.', 'lt' => 'Paprašykite prekių, pasirinkite kiekį, paklauskite kainos ir mandagiai sumokėkite.'],
                ],
                'note' => [
                    'title' => 'Before: Shopping at the Store',
                    'body' => implode("\n\n", [
                        'This capstone combines the shopping phrases from the unit.',
                        'Useful flow:',
                        '- Norėčiau duonos, prašau. = I would like bread, please.',
                        '- Du, prašau. = Two, please.',
                        '- Kiek kainuoja? = How much does it cost?',
                        '- Mokėsiu kortele. = I will pay by card.',
                        '- Ačiū, viso gero. = Thank you, goodbye.',
                        'Goal: complete a small shop interaction in short steps.',
                    ]),
                    'translations' => [
                        'title' => ['en' => 'Before: Shopping at the Store', 'lt' => 'Prieš scenarijų: Apsipirkimas parduotuvėje'],
                        'body' => ['en' => implode("\n\n", [
                            'This capstone combines the shopping phrases from the unit.',
                            'Useful flow:',
                            '- Norėčiau duonos, prašau. = I would like bread, please.',
                            '- Du, prašau. = Two, please.',
                            '- Kiek kainuoja? = How much does it cost?',
                            '- Mokėsiu kortele. = I will pay by card.',
                            '- Ačiū, viso gero. = Thank you, goodbye.',
                            'Goal: complete a small shop interaction in short steps.',
                        ]), 'lt' => implode("\n\n", [
                            'Šis pakartojimo scenarijus sujungia parduotuvės frazes iš šio skyriaus.',
                            'Naudinga seka:',
                            '- Norėčiau duonos, prašau.',
                            '- Du, prašau.',
                            '- Kiek kainuoja?',
                            '- Mokėsiu kortele.',
                            '- Ačiū, viso gero.',
                            'Tikslas: trumpais žingsniais atlikite mažą apsipirkimą.',
                        ])],
                    ],
                ],
                'scenes' => [
                    [
                        'slug' => 'prekiu-prasymas',
                        'title' => 'Ask for items',
                        'setting' => 'Rasa greets the learner in a small shop.',
                        'translations' => [
                            'title' => ['en' => 'Ask for items', 'lt' => 'Paprašykite prekių'],
                            'setting' => ['en' => 'Rasa greets the learner in a small shop.', 'lt' => 'Rasa pasitinka mokinį mažoje parduotuvėje.'],
                        ],
                        'lines' => [
                            ['target_text' => 'Laba diena. Ko norėsite?', 'support_translation' => 'Good day. What would you like?', 'trigger_goal' => null],
                            ['target_text' => 'Sveiki. Kuo galiu padėti?', 'support_translation' => 'Hello. How can I help?', 'trigger_goal' => null],
                            ['target_text' => 'Gerai. Turime šią prekę.', 'support_translation' => 'Good. We have this item.', 'trigger_goal' => 'capstone-ask-item', 'priority' => 100],
                            ['target_text' => 'Žinoma. Prašom.', 'support_translation' => 'Of course. Here you are.', 'trigger_goal' => 'capstone-ask-item', 'priority' => 100],
                        ],
                        'goals' => [[
                            'slug' => 'capstone-ask-item',
                            'label' => 'Ask for an item',
                            'intent' => 'The learner politely asks for one simple shop item.',
                            'example' => 'Norėčiau duonos, prašau.',
                            'next' => 'kiekio-pasirinkimas',
                            'translations' => [
                                'label' => ['en' => 'Ask for an item', 'lt' => 'Paprašykite prekės'],
                                'intent' => ['en' => 'The learner politely asks for one simple shop item.', 'lt' => 'Mokinys mandagiai paprašo vienos paprastos parduotuvės prekės.'],
                            ],
                        ]],
                    ],
                    [
                        'slug' => 'kiekio-pasirinkimas',
                        'title' => 'Choose quantity',
                        'setting' => 'Rasa asks how many items the learner wants.',
                        'translations' => [
                            'title' => ['en' => 'Choose quantity', 'lt' => 'Pasirinkite kiekį'],
                            'setting' => ['en' => 'Rasa asks how many items the learner wants.', 'lt' => 'Rasa klausia, kiek prekių mokinys nori.'],
                        ],
                        'lines' => [
                            ['target_text' => 'Kiek norėsite?', 'support_translation' => 'How many would you like?', 'trigger_goal' => null],
                            ['target_text' => 'Vieną ar du?', 'support_translation' => 'One or two?', 'trigger_goal' => null],
                            ['target_text' => 'Gerai. Kiekį supratau.', 'support_translation' => 'Good. I understood the quantity.', 'trigger_goal' => 'capstone-quantity', 'priority' => 100],
                            ['target_text' => 'Puiku. Tuoj paskaičiuosiu.', 'support_translation' => 'Great. I will calculate it now.', 'trigger_goal' => 'capstone-quantity', 'priority' => 100],
                        ],
                        'goals' => [[
                            'slug' => 'capstone-quantity',
                            'label' => 'Say the quantity',
                            'intent' => 'The learner says a small quantity for the requested item.',
                            'example' => 'Du, prašau.',
                            'next' => 'kaina-ir-mokejimas',
                            'translations' => [
                                'label' => ['en' => 'Say the quantity', 'lt' => 'Pasakykite kiekį'],
                                'intent' => ['en' => 'The learner says a small quantity for the requested item.', 'lt' => 'Mokinys pasako mažą prašomos prekės kiekį.'],
                            ],
                        ]],
                    ],
                    [
                        'slug' => 'kaina-ir-mokejimas',
                        'title' => 'Price and payment',
                        'setting' => 'Rasa gives the price and waits for payment.',
                        'translations' => [
                            'title' => ['en' => 'Price and payment', 'lt' => 'Kaina ir mokėjimas'],
                            'setting' => ['en' => 'Rasa gives the price and waits for payment.', 'lt' => 'Rasa pasako kainą ir laukia mokėjimo.'],
                        ],
                        'lines' => [
                            ['target_text' => 'Bus trys eurai. Kaip mokėsite?', 'support_translation' => 'That will be three euros. How will you pay?', 'trigger_goal' => null],
                            ['target_text' => 'Iš viso trys eurai.', 'support_translation' => 'Three euros in total.', 'trigger_goal' => null],
                            ['target_text' => 'Ačiū. Apmokėta.', 'support_translation' => 'Thank you. Paid.', 'trigger_goal' => 'capstone-pay', 'priority' => 100],
                            ['target_text' => 'Ačiū jums. Viso gero!', 'support_translation' => 'Thank you. Goodbye!', 'trigger_goal' => 'capstone-pay', 'priority' => 100],
                        ],
                        'goals' => [[
                            'slug' => 'capstone-pay',
                            'label' => 'Pay and finish',
                            'intent' => 'The learner says how they will pay, thanks the shop worker, or says goodbye.',
                            'example' => 'Mokėsiu kortele.',
                            'next' => null,
                            'translations' => [
                                'label' => ['en' => 'Pay and finish', 'lt' => 'Sumokėkite ir užbaikite'],
                                'intent' => ['en' => 'The learner says how they will pay, thanks the shop worker, or says goodbye.', 'lt' => 'Mokinys pasako, kaip mokės, padėkoja pardavėjai arba atsisveikina.'],
                            ],
                        ]],
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
            'Laba diena. Ko ieškote?' => 'Good day. What are you looking for?',
            'Sveiki. Ko norėsite?' => 'Hello. What would you like?',
            'Žinoma, štai duona.' => 'Of course, here is bread.',
            'Gerai, prašom.' => 'Good, here you are.',
            'Ko jums reikia?' => 'What do you need?',
            'Pasakykite, ko jums reikia.' => 'Say what you need.',
            'Supratau. Tuoj pažiūrėsiu.' => 'I understand. I will check now.',
            'Gerai. Padėsiu jums.' => 'Good. I will help you.',
            'Kiek norėsite?' => 'How many would you like?',
            'Vieną ar du?' => 'One or two?',
            'Gerai, du.' => 'Good, two.',
            'Puiku. Kiekį supratau.' => 'Great. I understood the quantity.',
            'Galite paklausti kainos.' => 'You can ask the price.',
            'Kokios prekės kainos norite paklausti?' => 'Which item price would you like to ask about?',
            'Pienas kainuoja vieną eurą.' => 'Milk costs one euro.',
            'Tai kainuoja du eurus.' => 'That costs two euros.',
            'Bus trys eurai. Kaip mokėsite?' => 'That will be three euros. How will you pay?',
            'Ar mokėsite kortele?' => 'Will you pay by card?',
            'Gerai. Kortele apmokėta.' => 'Good. Paid by card.',
            'Ačiū. Čekis čia.' => 'Thank you. The receipt is here.',
            default => $line,
        };
    }
}
