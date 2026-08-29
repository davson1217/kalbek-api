<?php

namespace Database\Seeders;

use App\ContentStatus;
use App\Models\Character;
use App\Models\Scenario;
use App\Models\Scene;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RestaurantScenarioSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $character = Character::query()->where('slug', 'rasa')->firstOrFail();

            $scenario = Scenario::query()->updateOrCreate(
                ['slug' => 'restoranas'],
                [
                    'character_id' => $character->id,
                    'title' => 'Restorane',
                    'subtitle' => 'At the restaurant',
                    'description' => 'You walk into a Lithuanian restaurant. Greet the waitress, get a table, read the menu, order food and drink, and pay the bill — all out loud.',
                    'emoji' => '🍽️',
                    'tone' => 'primary',
                    'start_scene_slug' => 'atvykimas',
                    'status' => ContentStatus::Published,
                    'sort_order' => 10,
                    'published_at' => now(),
                ],
            );

            $scenes = $this->upsertScenes($scenario);
            $this->replaceSceneContent($scenes);
        });
    }

    /**
     * @return Collection<string, Scene>
     */
    private function upsertScenes(Scenario $scenario): Collection
    {
        foreach ($this->scenes() as $index => $scene) {
            Scene::query()->updateOrCreate(
                [
                    'scenario_id' => $scenario->id,
                    'slug' => $scene['slug'],
                ],
                [
                    'setting' => $scene['setting'],
                    'sort_order' => ($index + 1) * 10,
                ],
            );
        }

        return $scenario->scenes()->get()->keyBy('slug');
    }

    /**
     * @param  Collection<string, Scene>  $scenes
     */
    private function replaceSceneContent(Collection $scenes): void
    {
        foreach ($this->scenes() as $sceneData) {
            /** @var Scene $scene */
            $scene = $scenes->get($sceneData['slug']);
            $scene->npcLines()->delete();
            $scene->props()->delete();

            foreach ($sceneData['lines'] as $index => $line) {
                $scene->npcLines()->create([
                    'lt' => $line['lt'],
                    'en' => $line['en'],
                    'sort_order' => ($index + 1) * 10,
                ]);
            }

            foreach ($sceneData['props'] ?? [] as $index => $prop) {
                $scene->props()->create([
                    'type' => 'menu_item',
                    'lt' => $prop['lt'],
                    'en' => $prop['en'],
                    'price' => $prop['price'],
                    'sort_order' => ($index + 1) * 10,
                ]);
            }

            foreach ($sceneData['goals'] as $index => $goal) {
                $scene->goals()->updateOrCreate(
                    ['slug' => $goal['slug']],
                    [
                        'next_scene_id' => $goal['next'] ? $scenes->get($goal['next'])?->id : null,
                        'label' => $goal['label'],
                        'intent' => $goal['intent'],
                        'example' => $goal['example'],
                        'sort_order' => ($index + 1) * 10,
                    ],
                );
            }
        }
    }

    private function scenes(): array
    {
        $menu = [
            ['lt' => 'Šaltibarščiai', 'en' => 'Cold beetroot soup', 'price' => '4,50 €'],
            ['lt' => 'Cepelinai su mėsa', 'en' => 'Potato dumplings with meat', 'price' => '9,90 €'],
            ['lt' => 'Kepta duona su sūriu', 'en' => 'Fried bread with cheese', 'price' => '5,20 €'],
            ['lt' => 'Bulviniai blynai', 'en' => 'Potato pancakes', 'price' => '7,40 €'],
            ['lt' => 'Vištienos kepsnys', 'en' => 'Grilled chicken', 'price' => '11,50 €'],
            ['lt' => 'Šakotis', 'en' => 'Tree cake (dessert)', 'price' => '3,80 €'],
        ];

        return [
            [
                'slug' => 'atvykimas',
                'setting' => 'You push open the door. Rasa, the waitress, looks up.',
                'lines' => [
                    ['lt' => 'Laba diena! Sveiki atvykę į mūsų restoraną.', 'en' => 'Good day! Welcome to our restaurant.'],
                    ['lt' => 'Sveiki! Kuo galiu jums padėti?', 'en' => 'Hello! How can I help you?'],
                    ['lt' => 'Labas vakaras! Malonu jus matyti. Ar turite rezervaciją?', 'en' => 'Good evening! Nice to see you. Do you have a reservation?'],
                ],
                'goals' => [
                    ['slug' => 'staliukas', 'label' => 'Greet her and ask for a table', 'intent' => 'Greet the waitress politely and ask for a table (optionally for two people).', 'example' => 'Laba diena! Norėčiau staliuko dviem, prašau.', 'next' => 'sodinimas'],
                    ['slug' => 'meniu-klausimas', 'label' => 'Ask what is on the menu', 'intent' => 'Greet her and ask what they have on the menu today.', 'example' => 'Sveiki! Ką turite valgiaraštyje?', 'next' => 'meniu'],
                ],
            ],
            [
                'slug' => 'sodinimas',
                'setting' => 'Rasa picks up two menus and steps out from behind the counter.',
                'lines' => [
                    ['lt' => 'Žinoma. Prašau sekite paskui mane — štai staliukas prie lango.', 'en' => 'Of course. Please follow me — here is a table by the window.'],
                    ['lt' => 'Puiku! Ar norite sėdėti prie lango, ar kampe?', 'en' => 'Great! Would you like to sit by the window or in the corner?'],
                    ['lt' => 'Turime laisvą staliuką. Prašom sėstis.', 'en' => 'We have a free table. Please have a seat.'],
                ],
                'goals' => [
                    ['slug' => 'aciu-meniu', 'label' => 'Thank her and ask for the menu', 'intent' => 'Thank her and ask if you could have the menu.', 'example' => 'Ačiū. Ar galėčiau gauti meniu?', 'next' => 'meniu'],
                    ['slug' => 'prie-lango', 'label' => 'Say you prefer the window seat', 'intent' => 'Say that you would prefer to sit by the window.', 'example' => 'Norėčiau sėdėti prie lango, prašau.', 'next' => 'meniu'],
                ],
            ],
            [
                'slug' => 'meniu',
                'setting' => 'The menu is on the table in front of you.',
                'lines' => [
                    ['lt' => 'Štai mūsų valgiaraštis. Šiandien rekomenduoju cepelinus.', 'en' => 'Here is our menu. Today I recommend the cepelinai.'],
                    ['lt' => 'Prašom, čia meniu. Ką norėtumėte užsisakyti?', 'en' => 'Here you are, the menu. What would you like to order?'],
                    ['lt' => 'Viskas šviežia. Ar jau žinote, ko norėsite?', 'en' => 'Everything is fresh. Do you already know what you would like?'],
                ],
                'props' => $menu,
                'goals' => [
                    ['slug' => 'uzsakymas', 'label' => 'Order a dish from the menu', 'intent' => 'Order one dish from the menu politely, naming a dish that appears on the menu (for example cepelinai, šaltibarščiai, bulviniai blynai).', 'example' => 'Norėčiau cepelinų su mėsa, prašau.', 'next' => 'gerimai'],
                    ['slug' => 'rekomendacija', 'label' => 'Ask her what she recommends', 'intent' => 'Ask the waitress what she recommends or what the most popular dish is.', 'example' => 'Ką jūs rekomenduotumėte?', 'next' => 'rekomendacija-atsakymas'],
                ],
            ],
            [
                'slug' => 'rekomendacija-atsakymas',
                'setting' => 'Rasa leans in a little, happy to be asked.',
                'lines' => [
                    ['lt' => 'Rekomenduoju cepelinus su mėsa — tai mūsų garsiausias patiekalas.', 'en' => 'I recommend cepelinai with meat — it is our most famous dish.'],
                    ['lt' => 'Vasarą visi renkasi šaltibarščius. Labai gaivu!', 'en' => 'In summer everyone chooses cold beetroot soup. Very refreshing!'],
                    ['lt' => 'Bulviniai blynai su grietine yra labai skanūs.', 'en' => 'The potato pancakes with sour cream are very tasty.'],
                ],
                'props' => $menu,
                'goals' => [
                    ['slug' => 'uzsakymas-2', 'label' => 'Order that dish', 'intent' => 'Accept the recommendation and order a dish from the menu politely.', 'example' => 'Gerai, tada prašau cepelinų.', 'next' => 'gerimai'],
                ],
            ],
            [
                'slug' => 'gerimai',
                'setting' => 'Rasa writes the order in her notepad.',
                'lines' => [
                    ['lt' => 'Puikus pasirinkimas. O ką gersite?', 'en' => 'Excellent choice. And what will you drink?'],
                    ['lt' => 'Gerai. Ar norėsite ko nors atsigerti?', 'en' => 'Alright. Would you like something to drink?'],
                    ['lt' => 'Užrašiau. Kokį gėrimą jums atnešti?', 'en' => 'Noted. What drink should I bring you?'],
                ],
                'goals' => [
                    ['slug' => 'gerimas', 'label' => 'Order a drink', 'intent' => 'Order a drink, for example water, tea, coffee, juice or beer.', 'example' => 'Prašau stiklinę vandens.', 'next' => 'desertas'],
                    ['slug' => 'vandens-klausimas', 'label' => 'Ask if the water is free', 'intent' => 'Ask whether the tap water is free or how much the water costs.', 'example' => 'Ar vanduo nemokamas?', 'next' => 'desertas'],
                ],
            ],
            [
                'slug' => 'desertas',
                'setting' => 'The food arrives, steaming. Later Rasa comes back.',
                'lines' => [
                    ['lt' => 'Skanaus! Vėliau — ar norėsite deserto?', 'en' => 'Enjoy your meal! Later — would you like dessert?'],
                    ['lt' => 'Ar viskas buvo skanu? Gal atnešti šakočio?', 'en' => 'Was everything tasty? Shall I bring some šakotis?'],
                    ['lt' => 'Matau, kad patiko. Ar paliksite vietos desertui?', 'en' => 'I see you liked it. Will you leave room for dessert?'],
                ],
                'goals' => [
                    ['slug' => 'taip-desertas', 'label' => 'Say yes and order dessert', 'intent' => 'Say yes and order a dessert, for example šakotis.', 'example' => 'Taip, prašau gabalėlį šakočio.', 'next' => 'saskaita'],
                    ['slug' => 'ne-desertas', 'label' => 'Politely decline', 'intent' => 'Politely decline dessert and say the meal was delicious.', 'example' => 'Ne, ačiū. Buvo labai skanu.', 'next' => 'saskaita'],
                ],
            ],
            [
                'slug' => 'saskaita',
                'setting' => 'Your plate is cleared. Time to settle up.',
                'lines' => [
                    ['lt' => 'Ar dar ko nors pageidausite?', 'en' => 'Would you like anything else?'],
                    ['lt' => 'Viskas gerai? Ar galiu dar kuo padėti?', 'en' => 'Is everything alright? Can I help with anything else?'],
                    ['lt' => 'Tikiuosi, kad patiko. Ar dar ko nors reikia?', 'en' => 'I hope you enjoyed it. Do you need anything else?'],
                ],
                'goals' => [
                    ['slug' => 'prasau-saskaita', 'label' => 'Ask for the bill', 'intent' => 'Ask politely for the bill.', 'example' => 'Prašau sąskaitą.', 'next' => 'mokejimas'],
                ],
            ],
            [
                'slug' => 'mokejimas',
                'setting' => 'Rasa brings the bill on a small wooden tray.',
                'lines' => [
                    ['lt' => 'Prašom, sąskaita — dvidešimt trys eurai. Mokėsite grynais ar kortele?', 'en' => 'Here you are, the bill — twenty three euros. Will you pay in cash or by card?'],
                    ['lt' => 'Iš viso dvidešimt trys eurai. Kaip norėtumėte mokėti?', 'en' => 'Twenty three euros in total. How would you like to pay?'],
                    ['lt' => 'Štai sąskaita. Ar galima kortele?', 'en' => 'Here is the bill. Card is fine?'],
                ],
                'goals' => [
                    ['slug' => 'kortele', 'label' => 'Say you will pay by card', 'intent' => 'Say that you will pay by card.', 'example' => 'Mokėsiu kortele, prašau.', 'next' => 'atsisveikinimas'],
                    ['slug' => 'grynais', 'label' => 'Say you will pay in cash', 'intent' => 'Say that you will pay in cash.', 'example' => 'Mokėsiu grynais.', 'next' => 'atsisveikinimas'],
                ],
            ],
            [
                'slug' => 'atsisveikinimas',
                'setting' => 'Payment done. Rasa walks you to the door.',
                'lines' => [
                    ['lt' => 'Ačiū, kad apsilankėte! Laukiame jūsų vėl.', 'en' => 'Thank you for visiting! We look forward to seeing you again.'],
                    ['lt' => 'Labai ačiū. Geros dienos!', 'en' => 'Thank you very much. Have a good day!'],
                    ['lt' => 'Ačiū! Iki kito karto.', 'en' => 'Thank you! Until next time.'],
                ],
                'goals' => [
                    ['slug' => 'sudie', 'label' => 'Thank her and say goodbye', 'intent' => 'Thank her and say goodbye.', 'example' => 'Ačiū jums! Viso gero.', 'next' => 'pabaiga'],
                ],
            ],
            [
                'slug' => 'pabaiga',
                'setting' => 'You step back out onto the street — a whole meal ordered in Lithuanian.',
                'lines' => [
                    ['lt' => 'Puiku! Tu ką tik pavalgei restorane lietuviškai.', 'en' => 'Great! You just ate at a restaurant in Lithuanian.'],
                ],
                'goals' => [],
            ],
        ];
    }
}
