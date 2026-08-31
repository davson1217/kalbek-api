<?php

namespace Database\Seeders;

use App\ContentStatus;
use App\Models\Character;
use App\Models\Goal;
use App\Models\Scenario;
use App\Models\Scene;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class A1ScenarioSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            foreach ($this->scenarios() as $scenarioData) {
                $character = Character::query()->where('slug', $scenarioData['character'])->firstOrFail();

                $scenario = Scenario::query()->updateOrCreate(
                    ['slug' => $scenarioData['slug']],
                    [
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

                $scenes = $this->upsertScenes($scenario, $scenarioData['scenes']);
                $this->replaceSceneContent($scenes, $scenarioData['scenes']);
            }
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $sceneData
     * @return Collection<string, Scene>
     */
    private function upsertScenes(Scenario $scenario, array $sceneData): Collection
    {
        foreach ($sceneData as $index => $scene) {
            $scenario->scenes()->updateOrCreate(
                ['slug' => $scene['slug']],
                [
                    'setting' => $scene['setting'],
                    'cefr_level' => 'a1',
                    'sort_order' => ($index + 1) * 10,
                ],
            );
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
            foreach ($scene['goals'] as $index => $goal) {
                $scenes->get($scene['slug'])->goals()->updateOrCreate(
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
            }
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
                $model->npcLines()->create([
                    'trigger_goal_id' => isset($line['trigger_goal']) ? $goals->get($line['trigger_goal'])?->id : null,
                    'lt' => $line['lt'],
                    'en' => $line['en'],
                    'cefr_level' => 'a1',
                    'priority' => $line['priority'] ?? 0,
                    'sort_order' => ($index + 1) * 10,
                ]);
            }

            foreach ($scene['props'] ?? [] as $index => $prop) {
                $model->props()->create([
                    'type' => $prop['type'] ?? 'menu_item',
                    'lt' => $prop['lt'],
                    'en' => $prop['en'],
                    'price' => $prop['price'] ?? null,
                    'sort_order' => ($index + 1) * 10,
                ]);
            }
        }
    }

    private function scenarios(): array
    {
        return [
            [
                'slug' => 'prisistatymas',
                'title' => 'Prisistatymas',
                'subtitle' => 'Introducing yourself',
                'description' => 'Say your name, where you are from, and what language you speak.',
                'emoji' => '👋',
                'character' => 'gabija',
                'tone' => 'primary',
                'sort_order' => 10,
                'start_scene' => 'pasisveikinimas',
                'scenes' => [
                    [
                        'slug' => 'pasisveikinimas',
                        'setting' => 'Gabija meets the learner for the first time.',
                        'lines' => [
                            ['lt' => 'Labas! Kuo tu vardu?', 'en' => 'Hi! What is your name?'],
                            ['lt' => 'Labas, koks jūsų vardas?', 'en' => 'Hi, what is your name?'],
                            ['lt' => 'Sveiki! Pasakykite savo vardą.', 'en' => 'Hello! Say your name.'],
                            ['lt' => 'Malonu susipažinti. Kaip jūs vadinatės?', 'en' => 'Nice to meet you. What are you called?'],
                            ['lt' => 'Malonu susipažinti.', 'en' => 'Nice to meet you.', 'trigger_goal' => 'intro-name', 'priority' => 100],
                            ['lt' => 'Ačiū. Malonu susipažinti.', 'en' => 'Thank you. Nice to meet you.', 'trigger_goal' => 'intro-name', 'priority' => 100],
                        ],
                        'goals' => [
                            ['slug' => 'intro-name', 'label' => 'Say your name', 'intent' => 'The learner says their name.', 'example' => 'Aš esu Jonas.', 'next' => 'is-kur'],
                        ],
                    ],
                    [
                        'slug' => 'is-kur',
                        'setting' => 'Gabija asks where the learner is from.',
                        'lines' => [
                            ['lt' => 'Iš kur jūs esate?', 'en' => 'Where are you from?'],
                            ['lt' => 'O iš kur tu esi?', 'en' => 'And where are you from?'],
                            ['lt' => 'Puiku. Iš kokios šalies jūs esate?', 'en' => 'Great. What country are you from?'],
                            ['lt' => 'Labai gerai. Ačiū, kad pasakėte.', 'en' => 'Very good. Thank you for saying that.', 'trigger_goal' => 'intro-country', 'priority' => 100],
                            ['lt' => 'Ačiū. Dabar suprantu.', 'en' => 'Thank you. Now I understand.', 'trigger_goal' => 'intro-country', 'priority' => 100],
                        ],
                        'goals' => [
                            ['slug' => 'intro-country', 'label' => 'Say where you are from', 'intent' => 'The learner says what country they are from.', 'example' => 'Aš esu iš Nigerijos.', 'next' => 'kalbos'],
                        ],
                    ],
                    [
                        'slug' => 'kalbos',
                        'setting' => 'Gabija asks about languages.',
                        'lines' => [
                            ['lt' => 'Kokia kalba jūs kalbate?', 'en' => 'What language do you speak?'],
                            ['lt' => 'Ar kalbate lietuviškai?', 'en' => 'Do you speak Lithuanian?'],
                            ['lt' => 'Aš truputį kalbu angliškai. O jūs?', 'en' => 'I speak a little English. And you?'],
                            ['lt' => 'Puiku. Malonu susipažinti!', 'en' => 'Great. Nice to meet you!', 'trigger_goal' => 'intro-language', 'priority' => 100],
                            ['lt' => 'Labai gerai. Ačiū už atsakymą.', 'en' => 'Very good. Thank you for the answer.', 'trigger_goal' => 'intro-language', 'priority' => 100],
                            ['lt' => 'Nieko tokio. Mokysimės po truputį.', 'en' => 'No problem. We will learn little by little.', 'trigger_goal' => 'intro-little-lt', 'priority' => 100],
                            ['lt' => 'Puiku. Truputį yra gera pradžia.', 'en' => 'Great. A little is a good start.', 'trigger_goal' => 'intro-little-lt', 'priority' => 100],
                        ],
                        'goals' => [
                            ['slug' => 'intro-language', 'label' => 'Say what language you speak', 'intent' => 'The learner says what language they speak.', 'example' => 'Aš kalbu angliškai.', 'next' => null],
                            ['slug' => 'intro-little-lt', 'label' => 'Say you speak a little Lithuanian', 'intent' => 'The learner says they speak a little Lithuanian.', 'example' => 'Aš truputį kalbu lietuviškai.', 'next' => null],
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'kavineje',
                'title' => 'Kavinėje',
                'subtitle' => 'At the cafe',
                'description' => 'Order a drink, ask for sugar or milk, and pay.',
                'emoji' => '☕',
                'character' => 'rasa',
                'tone' => 'amber',
                'sort_order' => 20,
                'start_scene' => 'uzsakymas',
                'scenes' => [
                    [
                        'slug' => 'uzsakymas',
                        'setting' => 'Rasa is at the cafe counter.',
                        'lines' => [
                            ['lt' => 'Laba diena! Ko norėsite?', 'en' => 'Good day! What would you like?'],
                            ['lt' => 'Sveiki. Ką užsakysite?', 'en' => 'Hello. What will you order?'],
                            ['lt' => 'Prašom. Kavos ar arbatos?', 'en' => 'Please. Coffee or tea?'],
                            ['lt' => 'Gerai, viena kava.', 'en' => 'Alright, one coffee.', 'trigger_goal' => 'cafe-coffee', 'priority' => 100],
                            ['lt' => 'Žinoma, kava.', 'en' => 'Of course, coffee.', 'trigger_goal' => 'cafe-coffee', 'priority' => 100],
                            ['lt' => 'Žinoma, viena arbata.', 'en' => 'Of course, one tea.', 'trigger_goal' => 'cafe-tea', 'priority' => 100],
                            ['lt' => 'Gerai, arbata.', 'en' => 'Alright, tea.', 'trigger_goal' => 'cafe-tea', 'priority' => 100],
                        ],
                        'goals' => [
                            ['slug' => 'cafe-coffee', 'label' => 'Order coffee', 'intent' => 'The learner orders one coffee politely.', 'example' => 'Norėčiau kavos, prašau.', 'next' => 'priedai'],
                            ['slug' => 'cafe-tea', 'label' => 'Order tea', 'intent' => 'The learner orders one tea politely.', 'example' => 'Norėčiau arbatos, prašau.', 'next' => 'priedai'],
                        ],
                    ],
                    [
                        'slug' => 'priedai',
                        'setting' => 'Rasa asks about additions to the drink.',
                        'lines' => [
                            ['lt' => 'Ar norėsite pieno arba cukraus?', 'en' => 'Would you like milk or sugar?'],
                            ['lt' => 'Jūsų gėrimas ruošiamas. Ko dar reikės?', 'en' => 'Your drink is being prepared. What else do you need?'],
                            ['lt' => 'Žinoma, įdėsiu cukraus.', 'en' => 'Of course, I will add sugar.', 'trigger_goal' => 'cafe-sugar', 'priority' => 100],
                            ['lt' => 'Gerai, cukraus.', 'en' => 'Alright, sugar.', 'trigger_goal' => 'cafe-sugar', 'priority' => 100],
                            ['lt' => 'Gerai, be pieno.', 'en' => 'Alright, without milk.', 'trigger_goal' => 'cafe-no-milk', 'priority' => 100],
                            ['lt' => 'Supratau, pieno nereikia.', 'en' => 'I understand, no milk is needed.', 'trigger_goal' => 'cafe-no-milk', 'priority' => 100],
                        ],
                        'goals' => [
                            ['slug' => 'cafe-sugar', 'label' => 'Ask for sugar', 'intent' => 'The learner asks for sugar.', 'example' => 'Prašau cukraus.', 'next' => 'mokejimas'],
                            ['slug' => 'cafe-no-milk', 'label' => 'Say no milk', 'intent' => 'The learner says they do not want milk.', 'example' => 'Be pieno, prašau.', 'next' => 'mokejimas'],
                        ],
                    ],
                    [
                        'slug' => 'mokejimas',
                        'setting' => 'The drink is ready and Rasa gives the price.',
                        'lines' => [
                            ['lt' => 'Bus du eurai. Kaip mokėsite?', 'en' => 'That will be two euros. How will you pay?'],
                            ['lt' => 'Jūsų gėrimas paruoštas. Du eurai.', 'en' => 'Your drink is ready. Two euros.'],
                            ['lt' => 'Ačiū. Štai jūsų kvitas.', 'en' => 'Thank you. Here is your receipt.', 'trigger_goal' => 'cafe-pay-card', 'priority' => 100],
                            ['lt' => 'Puiku. Kortele apmokėta.', 'en' => 'Great. Paid by card.', 'trigger_goal' => 'cafe-pay-card', 'priority' => 100],
                            ['lt' => 'Ačiū jums. Geros dienos!', 'en' => 'Thank you. Have a nice day!', 'trigger_goal' => 'cafe-thanks', 'priority' => 100],
                            ['lt' => 'Prašom. Iki pasimatymo!', 'en' => 'You are welcome. See you!', 'trigger_goal' => 'cafe-thanks', 'priority' => 100],
                        ],
                        'goals' => [
                            ['slug' => 'cafe-pay-card', 'label' => 'Pay by card', 'intent' => 'The learner says they will pay by card.', 'example' => 'Mokėsiu kortele.', 'next' => null],
                            ['slug' => 'cafe-thanks', 'label' => 'Thank and say goodbye', 'intent' => 'The learner thanks the worker and says goodbye.', 'example' => 'Ačiū, viso gero.', 'next' => null],
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'parduotuveje',
                'title' => 'Parduotuvėje',
                'subtitle' => 'At the shop',
                'description' => 'Ask where an item is, ask the price, and pay.',
                'emoji' => '🛒',
                'character' => 'gabija',
                'tone' => 'mint',
                'sort_order' => 30,
                'start_scene' => 'ieskau',
                'scenes' => [
                    [
                        'slug' => 'ieskau',
                        'setting' => 'Gabija works in a small shop.',
                        'lines' => [
                            ['lt' => 'Sveiki! Ar galiu padėti?', 'en' => 'Hello! Can I help?'],
                            ['lt' => 'Laba diena. Ko ieškote?', 'en' => 'Good day. What are you looking for?'],
                            ['lt' => 'Pienas yra šaldytuve, kairėje.', 'en' => 'Milk is in the fridge, on the left.', 'trigger_goal' => 'shop-milk', 'priority' => 100],
                            ['lt' => 'Žinoma. Pienas yra kairėje.', 'en' => 'Of course. Milk is on the left.', 'trigger_goal' => 'shop-milk', 'priority' => 100],
                            ['lt' => 'Duona yra ten, prie lango.', 'en' => 'Bread is there, by the window.', 'trigger_goal' => 'shop-bread', 'priority' => 100],
                            ['lt' => 'Taip, duona yra prie lango.', 'en' => 'Yes, bread is by the window.', 'trigger_goal' => 'shop-bread', 'priority' => 100],
                        ],
                        'goals' => [
                            ['slug' => 'shop-milk', 'label' => 'Ask where milk is', 'intent' => 'The learner asks where the milk is.', 'example' => 'Kur yra pienas?', 'next' => 'kaina'],
                            ['slug' => 'shop-bread', 'label' => 'Ask where bread is', 'intent' => 'The learner asks where the bread is.', 'example' => 'Kur yra duona?', 'next' => 'kaina'],
                        ],
                    ],
                    [
                        'slug' => 'kaina',
                        'setting' => 'The learner has found the item.',
                        'lines' => [
                            ['lt' => 'Ar dar ko nors reikės?', 'en' => 'Will you need anything else?'],
                            ['lt' => 'Radote? Puiku.', 'en' => 'Did you find it? Great.'],
                            ['lt' => 'Tai kainuoja vieną eurą.', 'en' => 'It costs one euro.', 'trigger_goal' => 'shop-price', 'priority' => 100],
                            ['lt' => 'Kaina yra vienas euras.', 'en' => 'The price is one euro.', 'trigger_goal' => 'shop-price', 'priority' => 100],
                            ['lt' => 'Taip, turime maišelių.', 'en' => 'Yes, we have bags.', 'trigger_goal' => 'shop-bag', 'priority' => 100],
                            ['lt' => 'Žinoma, štai maišelis.', 'en' => 'Of course, here is a bag.', 'trigger_goal' => 'shop-bag', 'priority' => 100],
                        ],
                        'goals' => [
                            ['slug' => 'shop-price', 'label' => 'Ask the price', 'intent' => 'The learner asks how much it costs.', 'example' => 'Kiek tai kainuoja?', 'next' => 'kasa'],
                            ['slug' => 'shop-bag', 'label' => 'Ask for a bag', 'intent' => 'The learner asks for a bag.', 'example' => 'Ar turite maišelį?', 'next' => 'kasa'],
                        ],
                    ],
                    [
                        'slug' => 'kasa',
                        'setting' => 'The learner is at the checkout.',
                        'lines' => [
                            ['lt' => 'Prašom prie kasos. Kaip mokėsite?', 'en' => 'Please come to the checkout. How will you pay?'],
                            ['lt' => 'Iš viso vienas euras.', 'en' => 'One euro in total.'],
                            ['lt' => 'Ačiū. Gero vakaro!', 'en' => 'Thank you. Have a good evening!', 'trigger_goal' => 'shop-pay', 'priority' => 100],
                            ['lt' => 'Puiku. Mokėjimas priimtas.', 'en' => 'Great. Payment accepted.', 'trigger_goal' => 'shop-pay', 'priority' => 100],
                            ['lt' => 'Prašom. Iki pasimatymo!', 'en' => 'You are welcome. See you!', 'trigger_goal' => 'shop-goodbye', 'priority' => 100],
                            ['lt' => 'Ačiū jums. Geros dienos!', 'en' => 'Thank you. Have a nice day!', 'trigger_goal' => 'shop-goodbye', 'priority' => 100],
                        ],
                        'goals' => [
                            ['slug' => 'shop-pay', 'label' => 'Pay by card', 'intent' => 'The learner says they will pay by card.', 'example' => 'Mokėsiu kortele.', 'next' => null],
                            ['slug' => 'shop-goodbye', 'label' => 'Say goodbye', 'intent' => 'The learner says thank you and goodbye.', 'example' => 'Ačiū, viso gero.', 'next' => null],
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'viesbutyje',
                'title' => 'Viešbutyje',
                'subtitle' => 'At the hotel',
                'description' => 'Check in, give your name, and ask about breakfast.',
                'emoji' => '🏨',
                'character' => 'rasa',
                'tone' => 'sky',
                'sort_order' => 60,
                'start_scene' => 'registratura',
                'scenes' => [
                    [
                        'slug' => 'registratura',
                        'setting' => 'Rasa is at the hotel reception desk.',
                        'lines' => [
                            ['lt' => 'Laba diena. Sveiki atvykę į viešbutį.', 'en' => 'Good day. Welcome to the hotel.'],
                            ['lt' => 'Sveiki. Ar turite rezervaciją?', 'en' => 'Hello. Do you have a reservation?'],
                            ['lt' => 'Gerai, patikrinsiu rezervaciją.', 'en' => 'Alright, I will check the reservation.', 'trigger_goal' => 'hotel-reservation', 'priority' => 100],
                            ['lt' => 'Taip, matau jūsų rezervaciją.', 'en' => 'Yes, I see your reservation.', 'trigger_goal' => 'hotel-reservation', 'priority' => 100],
                            ['lt' => 'Prašau pasakyti savo vardą.', 'en' => 'Please say your name.', 'trigger_goal' => 'hotel-room', 'priority' => 100],
                            ['lt' => 'Gerai. Reikia jūsų vardo.', 'en' => 'Alright. I need your name.', 'trigger_goal' => 'hotel-room', 'priority' => 100],
                        ],
                        'goals' => [
                            ['slug' => 'hotel-reservation', 'label' => 'Say you have a reservation', 'intent' => 'The learner says they have a reservation.', 'example' => 'Taip, turiu rezervaciją.', 'next' => 'vardas'],
                            ['slug' => 'hotel-room', 'label' => 'Ask for a room', 'intent' => 'The learner says they need a room.', 'example' => 'Man reikia kambario.', 'next' => 'vardas'],
                        ],
                    ],
                    [
                        'slug' => 'vardas',
                        'setting' => 'Rasa needs the learner name.',
                        'lines' => [
                            ['lt' => 'Koks jūsų vardas ir pavardė?', 'en' => 'What is your first and last name?'],
                            ['lt' => 'Prašau, jūsų vardas?', 'en' => 'Please, your name?'],
                            ['lt' => 'Ačiū. Dabar patikrinsiu informaciją.', 'en' => 'Thank you. I will now check the information.', 'trigger_goal' => 'hotel-name', 'priority' => 100],
                            ['lt' => 'Gerai. Radau jūsų kambarį.', 'en' => 'Alright. I found your room.', 'trigger_goal' => 'hotel-name', 'priority' => 100],
                        ],
                        'goals' => [
                            ['slug' => 'hotel-name', 'label' => 'Give your name', 'intent' => 'The learner says their name for check-in.', 'example' => 'Mano vardas Jonas Petrauskas.', 'next' => 'pusryciai'],
                        ],
                    ],
                    [
                        'slug' => 'pusryciai',
                        'setting' => 'The check-in is almost finished.',
                        'lines' => [
                            ['lt' => 'Ar turite klausimų?', 'en' => 'Do you have questions?'],
                            ['lt' => 'Štai jūsų raktas. Ko dar reikės?', 'en' => 'Here is your key. What else do you need?'],
                            ['lt' => 'Pusryčiai yra nuo septintos iki dešimtos.', 'en' => 'Breakfast is from seven to ten.', 'trigger_goal' => 'hotel-breakfast', 'priority' => 100],
                            ['lt' => 'Pusryčiai prasideda septintą valandą.', 'en' => 'Breakfast starts at seven o’clock.', 'trigger_goal' => 'hotel-breakfast', 'priority' => 100],
                            ['lt' => 'Laba naktis. Gero poilsio!', 'en' => 'Good night. Have a good rest!', 'trigger_goal' => 'hotel-thanks', 'priority' => 100],
                            ['lt' => 'Prašom. Gražaus vakaro!', 'en' => 'You are welcome. Have a nice evening!', 'trigger_goal' => 'hotel-thanks', 'priority' => 100],
                        ],
                        'goals' => [
                            ['slug' => 'hotel-breakfast', 'label' => 'Ask about breakfast', 'intent' => 'The learner asks when breakfast is.', 'example' => 'Kada yra pusryčiai?', 'next' => null],
                            ['slug' => 'hotel-thanks', 'label' => 'Thank the receptionist', 'intent' => 'The learner thanks the receptionist.', 'example' => 'Ačiū. Labanakt.', 'next' => null],
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'autobuse',
                'title' => 'Autobuse',
                'subtitle' => 'On the bus',
                'description' => 'Buy a ticket, say your destination, and ask for the stop.',
                'emoji' => '🚌',
                'character' => 'gabija',
                'tone' => 'berry',
                'sort_order' => 70,
                'start_scene' => 'bilietas',
                'scenes' => [
                    [
                        'slug' => 'bilietas',
                        'setting' => 'Gabija is the bus driver.',
                        'lines' => [
                            ['lt' => 'Laba diena. Kur važiuosite?', 'en' => 'Good day. Where are you going?'],
                            ['lt' => 'Sveiki. Reikia bilieto?', 'en' => 'Hello. Do you need a ticket?'],
                            ['lt' => 'Gerai, vienas bilietas į centrą.', 'en' => 'Alright, one ticket to the center.', 'trigger_goal' => 'bus-center', 'priority' => 100],
                            ['lt' => 'Žinoma, bilietas į centrą.', 'en' => 'Of course, a ticket to the center.', 'trigger_goal' => 'bus-center', 'priority' => 100],
                            ['lt' => 'Žinoma, vienas bilietas į stotį.', 'en' => 'Of course, one ticket to the station.', 'trigger_goal' => 'bus-station', 'priority' => 100],
                            ['lt' => 'Gerai, bilietas į stotį.', 'en' => 'Alright, a ticket to the station.', 'trigger_goal' => 'bus-station', 'priority' => 100],
                        ],
                        'goals' => [
                            ['slug' => 'bus-center', 'label' => 'Ask for a ticket to the center', 'intent' => 'The learner asks for one ticket to the city center.', 'example' => 'Vieną bilietą į centrą, prašau.', 'next' => 'kaina'],
                            ['slug' => 'bus-station', 'label' => 'Ask for a ticket to the station', 'intent' => 'The learner asks for one ticket to the station.', 'example' => 'Vieną bilietą į stotį, prašau.', 'next' => 'kaina'],
                        ],
                    ],
                    [
                        'slug' => 'kaina',
                        'setting' => 'The driver names the price.',
                        'lines' => [
                            ['lt' => 'Bilietas kainuoja vieną eurą.', 'en' => 'The ticket costs one euro.'],
                            ['lt' => 'Bus vienas euras.', 'en' => 'That will be one euro.'],
                            ['lt' => 'Ačiū. Štai jūsų bilietas.', 'en' => 'Thank you. Here is your ticket.', 'trigger_goal' => 'bus-pay', 'priority' => 100],
                            ['lt' => 'Puiku. Štai bilietas.', 'en' => 'Great. Here is the ticket.', 'trigger_goal' => 'bus-pay', 'priority' => 100],
                            ['lt' => 'Taip, galite mokėti kortele.', 'en' => 'Yes, you can pay by card.', 'trigger_goal' => 'bus-card-question', 'priority' => 100],
                            ['lt' => 'Taip, kortelė tinka.', 'en' => 'Yes, card is fine.', 'trigger_goal' => 'bus-card-question', 'priority' => 100],
                        ],
                        'goals' => [
                            ['slug' => 'bus-pay', 'label' => 'Pay for the ticket', 'intent' => 'The learner says they will pay.', 'example' => 'Prašom. Mokėsiu kortele.', 'next' => 'stotele'],
                            ['slug' => 'bus-card-question', 'label' => 'Ask if card is accepted', 'intent' => 'The learner asks if they can pay by card.', 'example' => 'Ar galima mokėti kortele?', 'next' => 'stotele'],
                        ],
                    ],
                    [
                        'slug' => 'stotele',
                        'setting' => 'The learner wants to know when to get off.',
                        'lines' => [
                            ['lt' => 'Atsisėskite, prašau.', 'en' => 'Please sit down.'],
                            ['lt' => 'Važiuosime apie dešimt minučių.', 'en' => 'We will ride for about ten minutes.'],
                            ['lt' => 'Jūsų stotelė bus netrukus.', 'en' => 'Your stop will be soon.', 'trigger_goal' => 'bus-stop', 'priority' => 100],
                            ['lt' => 'Pasakysiu, kada reikės išlipti.', 'en' => 'I will tell you when you need to get off.', 'trigger_goal' => 'bus-stop', 'priority' => 100],
                            ['lt' => 'Nėra už ką. Geros kelionės!', 'en' => 'You are welcome. Have a good trip!', 'trigger_goal' => 'bus-thanks', 'priority' => 100],
                            ['lt' => 'Prašom. Geros dienos!', 'en' => 'You are welcome. Have a nice day!', 'trigger_goal' => 'bus-thanks', 'priority' => 100],
                        ],
                        'goals' => [
                            ['slug' => 'bus-stop', 'label' => 'Ask where to get off', 'intent' => 'The learner asks which stop they need.', 'example' => 'Kur man išlipti?', 'next' => null],
                            ['slug' => 'bus-thanks', 'label' => 'Say thank you', 'intent' => 'The learner thanks the driver.', 'example' => 'Ačiū, geros dienos.', 'next' => null],
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'mieste',
                'title' => 'Mieste',
                'subtitle' => 'In the city',
                'description' => 'Ask where a place is and understand simple directions.',
                'emoji' => '🗺️',
                'character' => 'rasa',
                'tone' => 'primary',
                'sort_order' => 80,
                'start_scene' => 'pagalba',
                'scenes' => [
                    [
                        'slug' => 'pagalba',
                        'setting' => 'Rasa is walking in the city.',
                        'lines' => [
                            ['lt' => 'Laba diena. Ar jums reikia pagalbos?', 'en' => 'Good day. Do you need help?'],
                            ['lt' => 'Sveiki. Ar pasiklydote?', 'en' => 'Hello. Are you lost?'],
                            ['lt' => 'Žinoma, padėsiu.', 'en' => 'Of course, I will help.', 'trigger_goal' => 'city-help', 'priority' => 100],
                            ['lt' => 'Taip, galiu padėti.', 'en' => 'Yes, I can help.', 'trigger_goal' => 'city-help', 'priority' => 100],
                            ['lt' => 'Gerai. Kur norite eiti?', 'en' => 'Alright. Where do you want to go?', 'trigger_goal' => 'city-lost', 'priority' => 100],
                            ['lt' => 'Suprantu. Kokios vietos ieškote?', 'en' => 'I understand. What place are you looking for?', 'trigger_goal' => 'city-lost', 'priority' => 100],
                        ],
                        'goals' => [
                            ['slug' => 'city-help', 'label' => 'Ask for help', 'intent' => 'The learner politely asks for help.', 'example' => 'Atsiprašau, galite padėti?', 'next' => 'vieta'],
                            ['slug' => 'city-lost', 'label' => 'Say you are lost', 'intent' => 'The learner says they are lost.', 'example' => 'Aš pasiklydau.', 'next' => 'vieta'],
                        ],
                    ],
                    [
                        'slug' => 'vieta',
                        'setting' => 'Rasa asks what place the learner needs.',
                        'lines' => [
                            ['lt' => 'Ko jūs ieškote?', 'en' => 'What are you looking for?'],
                            ['lt' => 'Kokia vieta jums reikalinga?', 'en' => 'What place do you need?'],
                            ['lt' => 'Stotis yra tiesiai ir tada į kairę.', 'en' => 'The station is straight ahead and then left.', 'trigger_goal' => 'city-station', 'priority' => 100],
                            ['lt' => 'Eikite tiesiai. Stotis bus kairėje.', 'en' => 'Go straight. The station will be on the left.', 'trigger_goal' => 'city-station', 'priority' => 100],
                            ['lt' => 'Vaistinė yra šalia parduotuvės.', 'en' => 'The pharmacy is next to the shop.', 'trigger_goal' => 'city-pharmacy', 'priority' => 100],
                            ['lt' => 'Vaistinė yra netoli, dešinėje.', 'en' => 'The pharmacy is nearby, on the right.', 'trigger_goal' => 'city-pharmacy', 'priority' => 100],
                        ],
                        'goals' => [
                            ['slug' => 'city-station', 'label' => 'Ask where the station is', 'intent' => 'The learner asks where the station is.', 'example' => 'Kur yra stotis?', 'next' => 'patikslinimas'],
                            ['slug' => 'city-pharmacy', 'label' => 'Ask where the pharmacy is', 'intent' => 'The learner asks where the pharmacy is.', 'example' => 'Kur yra vaistinė?', 'next' => 'patikslinimas'],
                        ],
                    ],
                    [
                        'slug' => 'patikslinimas',
                        'setting' => 'The learner checks the direction.',
                        'lines' => [
                            ['lt' => 'Ar aišku?', 'en' => 'Is it clear?'],
                            ['lt' => 'Eikite lėtai. Tai netoli.', 'en' => 'Walk slowly. It is not far.'],
                            ['lt' => 'Taip, eikite tiesiai.', 'en' => 'Yes, go straight.', 'trigger_goal' => 'city-straight', 'priority' => 100],
                            ['lt' => 'Taip, eikite tiesiai toliau.', 'en' => 'Yes, keep going straight.', 'trigger_goal' => 'city-straight', 'priority' => 100],
                            ['lt' => 'Prašom. Sėkmės!', 'en' => 'You are welcome. Good luck!', 'trigger_goal' => 'city-thanks', 'priority' => 100],
                            ['lt' => 'Nėra už ką. Iki pasimatymo!', 'en' => 'You are welcome. See you!', 'trigger_goal' => 'city-thanks', 'priority' => 100],
                        ],
                        'goals' => [
                            ['slug' => 'city-straight', 'label' => 'Confirm the direction', 'intent' => 'The learner confirms they should go straight.', 'example' => 'Ar man eiti tiesiai?', 'next' => null],
                            ['slug' => 'city-thanks', 'label' => 'Thank the person', 'intent' => 'The learner thanks the person for help.', 'example' => 'Ačiū už pagalbą.', 'next' => null],
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'pas-gydytoja',
                'title' => 'Pas gydytoją',
                'subtitle' => 'At the doctor',
                'description' => 'Say what hurts, answer a simple question, and thank the doctor.',
                'emoji' => '🩺',
                'character' => 'gabija',
                'tone' => 'mint',
                'sort_order' => 90,
                'start_scene' => 'kabinetas',
                'scenes' => [
                    [
                        'slug' => 'kabinetas',
                        'setting' => 'Gabija is a doctor in a small clinic.',
                        'lines' => [
                            ['lt' => 'Laba diena. Kas jums yra?', 'en' => 'Good day. What is the matter?'],
                            ['lt' => 'Sveiki. Kaip jaučiatės?', 'en' => 'Hello. How do you feel?'],
                            ['lt' => 'Suprantu. Ar turite temperatūros?', 'en' => 'I understand. Do you have a fever?', 'trigger_goal' => 'doctor-headache', 'priority' => 100],
                            ['lt' => 'Gerai. Ar labai skauda?', 'en' => 'Alright. Does it hurt a lot?', 'trigger_goal' => 'doctor-headache', 'priority' => 100],
                            ['lt' => 'Gerai. Ar skauda stipriai?', 'en' => 'Alright. Does it hurt a lot?', 'trigger_goal' => 'doctor-throat', 'priority' => 100],
                            ['lt' => 'Suprantu. Ar dar kosėjate?', 'en' => 'I understand. Are you also coughing?', 'trigger_goal' => 'doctor-throat', 'priority' => 100],
                        ],
                        'goals' => [
                            ['slug' => 'doctor-headache', 'label' => 'Say your head hurts', 'intent' => 'The learner says they have a headache.', 'example' => 'Man skauda galvą.', 'next' => 'klausimas'],
                            ['slug' => 'doctor-throat', 'label' => 'Say your throat hurts', 'intent' => 'The learner says their throat hurts.', 'example' => 'Man skauda gerklę.', 'next' => 'klausimas'],
                        ],
                    ],
                    [
                        'slug' => 'klausimas',
                        'setting' => 'Gabija asks one simple health question.',
                        'lines' => [
                            ['lt' => 'Ar turite temperatūros?', 'en' => 'Do you have a fever?'],
                            ['lt' => 'Ar kosėjate?', 'en' => 'Are you coughing?'],
                            ['lt' => 'Gerai, užrašysiu.', 'en' => 'Alright, I will write it down.', 'trigger_goal' => 'doctor-yes-fever', 'priority' => 100],
                            ['lt' => 'Gerai, turite temperatūros.', 'en' => 'Alright, you have a fever.', 'trigger_goal' => 'doctor-yes-fever', 'priority' => 100],
                            ['lt' => 'Gerai. Tai skamba nesunkiai.', 'en' => 'Alright. That sounds mild.', 'trigger_goal' => 'doctor-no-fever', 'priority' => 100],
                            ['lt' => 'Gerai, temperatūros nėra.', 'en' => 'Alright, there is no fever.', 'trigger_goal' => 'doctor-no-fever', 'priority' => 100],
                        ],
                        'goals' => [
                            ['slug' => 'doctor-yes-fever', 'label' => 'Say yes, you have a fever', 'intent' => 'The learner says yes, they have a fever.', 'example' => 'Taip, turiu temperatūros.', 'next' => 'patarimas'],
                            ['slug' => 'doctor-no-fever', 'label' => 'Say no fever', 'intent' => 'The learner says they do not have a fever.', 'example' => 'Ne, temperatūros neturiu.', 'next' => 'patarimas'],
                        ],
                    ],
                    [
                        'slug' => 'patarimas',
                        'setting' => 'Gabija gives simple advice.',
                        'lines' => [
                            ['lt' => 'Gerkite vandens ir ilsėkitės.', 'en' => 'Drink water and rest.'],
                            ['lt' => 'Štai receptas. Ar turite klausimų?', 'en' => 'Here is a prescription. Do you have questions?'],
                            ['lt' => 'Vaistus vartokite du kartus per dieną.', 'en' => 'Take the medicine twice a day.', 'trigger_goal' => 'doctor-medicine', 'priority' => 100],
                            ['lt' => 'Gerkite vaistus ryte ir vakare.', 'en' => 'Take the medicine in the morning and evening.', 'trigger_goal' => 'doctor-medicine', 'priority' => 100],
                            ['lt' => 'Prašom. Linkiu pasveikti!', 'en' => 'You are welcome. I hope you get better!', 'trigger_goal' => 'doctor-thanks', 'priority' => 100],
                            ['lt' => 'Nėra už ką. Gero poilsio!', 'en' => 'You are welcome. Have a good rest!', 'trigger_goal' => 'doctor-thanks', 'priority' => 100],
                        ],
                        'goals' => [
                            ['slug' => 'doctor-medicine', 'label' => 'Ask how to take medicine', 'intent' => 'The learner asks how to take the medicine.', 'example' => 'Kaip vartoti vaistus?', 'next' => null],
                            ['slug' => 'doctor-thanks', 'label' => 'Thank the doctor', 'intent' => 'The learner thanks the doctor.', 'example' => 'Ačiū, viso gero.', 'next' => null],
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'klaseje',
                'title' => 'Klasėje',
                'subtitle' => 'In the classroom',
                'description' => 'Greet the teacher, say you do not understand, and ask to repeat.',
                'emoji' => '🎓',
                'character' => 'rasa',
                'tone' => 'berry',
                'sort_order' => 100,
                'start_scene' => 'pamoka',
                'scenes' => [
                    [
                        'slug' => 'pamoka',
                        'setting' => 'Rasa is a teacher starting a beginner Lithuanian class.',
                        'lines' => [
                            ['lt' => 'Labas rytas, klase!', 'en' => 'Good morning, class!'],
                            ['lt' => 'Sveiki. Pradedame pamoką.', 'en' => 'Hello. We are starting the lesson.'],
                            ['lt' => 'Labas rytas. Prašau atsisėsti.', 'en' => 'Good morning. Please sit down.', 'trigger_goal' => 'class-greet', 'priority' => 100],
                            ['lt' => 'Labas rytas. Smagu jus matyti.', 'en' => 'Good morning. Nice to see you.', 'trigger_goal' => 'class-greet', 'priority' => 100],
                            ['lt' => 'Gerai. Atsiverskite knygą.', 'en' => 'Alright. Open the book.', 'trigger_goal' => 'class-ready', 'priority' => 100],
                            ['lt' => 'Puiku. Galime pradėti.', 'en' => 'Great. We can start.', 'trigger_goal' => 'class-ready', 'priority' => 100],
                        ],
                        'goals' => [
                            ['slug' => 'class-greet', 'label' => 'Greet the teacher', 'intent' => 'The learner greets the teacher in the morning.', 'example' => 'Labas rytas, mokytoja.', 'next' => 'nesuprantu'],
                            ['slug' => 'class-ready', 'label' => 'Say you are ready', 'intent' => 'The learner says they are ready.', 'example' => 'Aš pasiruošęs.', 'next' => 'nesuprantu'],
                        ],
                    ],
                    [
                        'slug' => 'nesuprantu',
                        'setting' => 'The teacher says a new word.',
                        'lines' => [
                            ['lt' => 'Pakartokite: ačiū.', 'en' => 'Repeat: thank you.'],
                            ['lt' => 'Dabar sakome: prašau.', 'en' => 'Now we say: please.'],
                            ['lt' => 'Nieko tokio. Pakartosiu lėčiau.', 'en' => 'No problem. I will repeat more slowly.', 'trigger_goal' => 'class-not-understand', 'priority' => 100],
                            ['lt' => 'Gerai. Pasakysiu dar kartą.', 'en' => 'Alright. I will say it again.', 'trigger_goal' => 'class-not-understand', 'priority' => 100],
                            ['lt' => 'Žinoma. Sakau dar kartą: ačiū.', 'en' => 'Of course. I say again: thank you.', 'trigger_goal' => 'class-repeat', 'priority' => 100],
                            ['lt' => 'Taip, pakartoju: ačiū.', 'en' => 'Yes, I repeat: thank you.', 'trigger_goal' => 'class-repeat', 'priority' => 100],
                        ],
                        'goals' => [
                            ['slug' => 'class-not-understand', 'label' => 'Say you do not understand', 'intent' => 'The learner says they do not understand.', 'example' => 'Aš nesuprantu.', 'next' => 'pabaiga'],
                            ['slug' => 'class-repeat', 'label' => 'Ask to repeat', 'intent' => 'The learner asks the teacher to repeat.', 'example' => 'Prašau pakartoti.', 'next' => 'pabaiga'],
                        ],
                    ],
                    [
                        'slug' => 'pabaiga',
                        'setting' => 'The short class is ending.',
                        'lines' => [
                            ['lt' => 'Labai gerai. Pamoka baigta.', 'en' => 'Very good. The lesson is finished.'],
                            ['lt' => 'Šaunu. Iki kitos pamokos.', 'en' => 'Great. Until the next lesson.'],
                            ['lt' => 'Ačiū jums. Iki pasimatymo!', 'en' => 'Thank you. See you!', 'trigger_goal' => 'class-thanks', 'priority' => 100],
                            ['lt' => 'Prašom. Puikiai dirbote.', 'en' => 'You are welcome. You worked very well.', 'trigger_goal' => 'class-thanks', 'priority' => 100],
                            ['lt' => 'Iki rytojaus!', 'en' => 'See you tomorrow!', 'trigger_goal' => 'class-goodbye', 'priority' => 100],
                            ['lt' => 'Viso gero. Iki kitos pamokos!', 'en' => 'Goodbye. Until the next lesson!', 'trigger_goal' => 'class-goodbye', 'priority' => 100],
                        ],
                        'goals' => [
                            ['slug' => 'class-thanks', 'label' => 'Thank the teacher', 'intent' => 'The learner thanks the teacher.', 'example' => 'Ačiū, mokytoja.', 'next' => null],
                            ['slug' => 'class-goodbye', 'label' => 'Say goodbye', 'intent' => 'The learner says goodbye to the teacher.', 'example' => 'Iki pasimatymo.', 'next' => null],
                        ],
                    ],
                ],
            ],
        ];
    }
}
