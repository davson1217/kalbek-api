<?php

namespace Database\Seeders;

use App\ContentStatus;
use App\Models\Character;
use App\Models\Goal;
use App\Models\Language;
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
            $lithuanian = Language::query()->where('code', 'lt')->firstOrFail();

            foreach ($this->scenarios() as $scenarioData) {
                $character = Character::query()->where('slug', $scenarioData['character'])->firstOrFail();

                $scenario = Scenario::query()->updateOrCreate(
                    ['slug' => $scenarioData['slug']],
                    [
                        'language_id' => $lithuanian->id,
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
                    'target_text' => $line['target_text'],
                    'support_translation' => $line['support_translation'],
                    'cefr_level' => 'a1',
                    'priority' => $line['priority'] ?? 0,
                    'sort_order' => ($index + 1) * 10,
                ]);
            }

            foreach ($scene['props'] ?? [] as $index => $prop) {
                $model->props()->create([
                    'type' => $prop['type'] ?? 'menu_item',
                    'target_text' => $prop['target_text'],
                    'support_translation' => $prop['support_translation'],
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
                            ['target_text' => 'Labas! Kuo tu vardu?', 'support_translation' => 'Hi! What is your name?'],
                            ['target_text' => 'Labas, koks jūsų vardas?', 'support_translation' => 'Hi, what is your name?'],
                            ['target_text' => 'Sveiki! Pasakykite savo vardą.', 'support_translation' => 'Hello! Say your name.'],
                            ['target_text' => 'Malonu susipažinti. Kaip jūs vadinatės?', 'support_translation' => 'Nice to meet you. What are you called?'],
                            ['target_text' => 'Malonu susipažinti.', 'support_translation' => 'Nice to meet you.', 'trigger_goal' => 'intro-name', 'priority' => 100],
                            ['target_text' => 'Ačiū. Malonu susipažinti.', 'support_translation' => 'Thank you. Nice to meet you.', 'trigger_goal' => 'intro-name', 'priority' => 100],
                        ],
                        'goals' => [
                            ['slug' => 'intro-name', 'label' => 'Say your name', 'intent' => 'The learner says their name.', 'example' => 'Aš esu Jonas.', 'next' => 'is-kur'],
                        ],
                    ],
                    [
                        'slug' => 'is-kur',
                        'setting' => 'Gabija asks where the learner is from.',
                        'lines' => [
                            ['target_text' => 'Iš kur jūs esate?', 'support_translation' => 'Where are you from?'],
                            ['target_text' => 'O iš kur tu esi?', 'support_translation' => 'And where are you from?'],
                            ['target_text' => 'Puiku. Iš kokios šalies jūs esate?', 'support_translation' => 'Great. What country are you from?'],
                            ['target_text' => 'Labai gerai. Ačiū, kad pasakėte.', 'support_translation' => 'Very good. Thank you for saying that.', 'trigger_goal' => 'intro-country', 'priority' => 100],
                            ['target_text' => 'Ačiū. Dabar suprantu.', 'support_translation' => 'Thank you. Now I understand.', 'trigger_goal' => 'intro-country', 'priority' => 100],
                        ],
                        'goals' => [
                            ['slug' => 'intro-country', 'label' => 'Say where you are from', 'intent' => 'The learner says what country they are from.', 'example' => 'Aš esu iš Nigerijos.', 'next' => 'kalbos'],
                        ],
                    ],
                    [
                        'slug' => 'kalbos',
                        'setting' => 'Gabija asks about languages.',
                        'lines' => [
                            ['target_text' => 'Kokia kalba jūs kalbate?', 'support_translation' => 'What language do you speak?'],
                            ['target_text' => 'Ar kalbate lietuviškai?', 'support_translation' => 'Do you speak Lithuanian?'],
                            ['target_text' => 'Aš truputį kalbu angliškai. O jūs?', 'support_translation' => 'I speak a little English. And you?'],
                            ['target_text' => 'Puiku. Malonu susipažinti!', 'support_translation' => 'Great. Nice to meet you!', 'trigger_goal' => 'intro-language', 'priority' => 100],
                            ['target_text' => 'Labai gerai. Ačiū už atsakymą.', 'support_translation' => 'Very good. Thank you for the answer.', 'trigger_goal' => 'intro-language', 'priority' => 100],
                            ['target_text' => 'Nieko tokio. Mokysimės po truputį.', 'support_translation' => 'No problem. We will learn little by little.', 'trigger_goal' => 'intro-little-lt', 'priority' => 100],
                            ['target_text' => 'Puiku. Truputį yra gera pradžia.', 'support_translation' => 'Great. A little is a good start.', 'trigger_goal' => 'intro-little-lt', 'priority' => 100],
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
                            ['target_text' => 'Laba diena! Ko norėsite?', 'support_translation' => 'Good day! What would you like?'],
                            ['target_text' => 'Sveiki. Ką užsakysite?', 'support_translation' => 'Hello. What will you order?'],
                            ['target_text' => 'Prašom. Kavos ar arbatos?', 'support_translation' => 'Please. Coffee or tea?'],
                            ['target_text' => 'Gerai, viena kava.', 'support_translation' => 'Alright, one coffee.', 'trigger_goal' => 'cafe-coffee', 'priority' => 100],
                            ['target_text' => 'Žinoma, kava.', 'support_translation' => 'Of course, coffee.', 'trigger_goal' => 'cafe-coffee', 'priority' => 100],
                            ['target_text' => 'Žinoma, viena arbata.', 'support_translation' => 'Of course, one tea.', 'trigger_goal' => 'cafe-tea', 'priority' => 100],
                            ['target_text' => 'Gerai, arbata.', 'support_translation' => 'Alright, tea.', 'trigger_goal' => 'cafe-tea', 'priority' => 100],
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
                            ['target_text' => 'Ar norėsite pieno arba cukraus?', 'support_translation' => 'Would you like milk or sugar?'],
                            ['target_text' => 'Jūsų gėrimas ruošiamas. Ko dar reikės?', 'support_translation' => 'Your drink is being prepared. What else do you need?'],
                            ['target_text' => 'Žinoma, įdėsiu cukraus.', 'support_translation' => 'Of course, I will add sugar.', 'trigger_goal' => 'cafe-sugar', 'priority' => 100],
                            ['target_text' => 'Gerai, cukraus.', 'support_translation' => 'Alright, sugar.', 'trigger_goal' => 'cafe-sugar', 'priority' => 100],
                            ['target_text' => 'Gerai, be pieno.', 'support_translation' => 'Alright, without milk.', 'trigger_goal' => 'cafe-no-milk', 'priority' => 100],
                            ['target_text' => 'Supratau, pieno nereikia.', 'support_translation' => 'I understand, no milk is needed.', 'trigger_goal' => 'cafe-no-milk', 'priority' => 100],
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
                            ['target_text' => 'Bus du eurai. Kaip mokėsite?', 'support_translation' => 'That will be two euros. How will you pay?'],
                            ['target_text' => 'Jūsų gėrimas paruoštas. Du eurai.', 'support_translation' => 'Your drink is ready. Two euros.'],
                            ['target_text' => 'Ačiū. Štai jūsų kvitas.', 'support_translation' => 'Thank you. Here is your receipt.', 'trigger_goal' => 'cafe-pay-card', 'priority' => 100],
                            ['target_text' => 'Puiku. Kortele apmokėta.', 'support_translation' => 'Great. Paid by card.', 'trigger_goal' => 'cafe-pay-card', 'priority' => 100],
                            ['target_text' => 'Ačiū jums. Geros dienos!', 'support_translation' => 'Thank you. Have a nice day!', 'trigger_goal' => 'cafe-thanks', 'priority' => 100],
                            ['target_text' => 'Prašom. Iki pasimatymo!', 'support_translation' => 'You are welcome. See you!', 'trigger_goal' => 'cafe-thanks', 'priority' => 100],
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
                            ['target_text' => 'Sveiki! Ar galiu padėti?', 'support_translation' => 'Hello! Can I help?'],
                            ['target_text' => 'Laba diena. Ko ieškote?', 'support_translation' => 'Good day. What are you looking for?'],
                            ['target_text' => 'Pienas yra šaldytuve, kairėje.', 'support_translation' => 'Milk is in the fridge, on the left.', 'trigger_goal' => 'shop-milk', 'priority' => 100],
                            ['target_text' => 'Žinoma. Pienas yra kairėje.', 'support_translation' => 'Of course. Milk is on the left.', 'trigger_goal' => 'shop-milk', 'priority' => 100],
                            ['target_text' => 'Duona yra ten, prie lango.', 'support_translation' => 'Bread is there, by the window.', 'trigger_goal' => 'shop-bread', 'priority' => 100],
                            ['target_text' => 'Taip, duona yra prie lango.', 'support_translation' => 'Yes, bread is by the window.', 'trigger_goal' => 'shop-bread', 'priority' => 100],
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
                            ['target_text' => 'Ar dar ko nors reikės?', 'support_translation' => 'Will you need anything else?'],
                            ['target_text' => 'Radote? Puiku.', 'support_translation' => 'Did you find it? Great.'],
                            ['target_text' => 'Tai kainuoja vieną eurą.', 'support_translation' => 'It costs one euro.', 'trigger_goal' => 'shop-price', 'priority' => 100],
                            ['target_text' => 'Kaina yra vienas euras.', 'support_translation' => 'The price is one euro.', 'trigger_goal' => 'shop-price', 'priority' => 100],
                            ['target_text' => 'Taip, turime maišelių.', 'support_translation' => 'Yes, we have bags.', 'trigger_goal' => 'shop-bag', 'priority' => 100],
                            ['target_text' => 'Žinoma, štai maišelis.', 'support_translation' => 'Of course, here is a bag.', 'trigger_goal' => 'shop-bag', 'priority' => 100],
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
                            ['target_text' => 'Prašom prie kasos. Kaip mokėsite?', 'support_translation' => 'Please come to the checkout. How will you pay?'],
                            ['target_text' => 'Iš viso vienas euras.', 'support_translation' => 'One euro in total.'],
                            ['target_text' => 'Ačiū. Gero vakaro!', 'support_translation' => 'Thank you. Have a good evening!', 'trigger_goal' => 'shop-pay', 'priority' => 100],
                            ['target_text' => 'Puiku. Mokėjimas priimtas.', 'support_translation' => 'Great. Payment accepted.', 'trigger_goal' => 'shop-pay', 'priority' => 100],
                            ['target_text' => 'Prašom. Iki pasimatymo!', 'support_translation' => 'You are welcome. See you!', 'trigger_goal' => 'shop-goodbye', 'priority' => 100],
                            ['target_text' => 'Ačiū jums. Geros dienos!', 'support_translation' => 'Thank you. Have a nice day!', 'trigger_goal' => 'shop-goodbye', 'priority' => 100],
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
                            ['target_text' => 'Laba diena. Sveiki atvykę į viešbutį.', 'support_translation' => 'Good day. Welcome to the hotel.'],
                            ['target_text' => 'Sveiki. Ar turite rezervaciją?', 'support_translation' => 'Hello. Do you have a reservation?'],
                            ['target_text' => 'Gerai, patikrinsiu rezervaciją.', 'support_translation' => 'Alright, I will check the reservation.', 'trigger_goal' => 'hotel-reservation', 'priority' => 100],
                            ['target_text' => 'Taip, matau jūsų rezervaciją.', 'support_translation' => 'Yes, I see your reservation.', 'trigger_goal' => 'hotel-reservation', 'priority' => 100],
                            ['target_text' => 'Prašau pasakyti savo vardą.', 'support_translation' => 'Please say your name.', 'trigger_goal' => 'hotel-room', 'priority' => 100],
                            ['target_text' => 'Gerai. Reikia jūsų vardo.', 'support_translation' => 'Alright. I need your name.', 'trigger_goal' => 'hotel-room', 'priority' => 100],
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
                            ['target_text' => 'Koks jūsų vardas ir pavardė?', 'support_translation' => 'What is your first and last name?'],
                            ['target_text' => 'Prašau, jūsų vardas?', 'support_translation' => 'Please, your name?'],
                            ['target_text' => 'Ačiū. Dabar patikrinsiu informaciją.', 'support_translation' => 'Thank you. I will now check the information.', 'trigger_goal' => 'hotel-name', 'priority' => 100],
                            ['target_text' => 'Gerai. Radau jūsų kambarį.', 'support_translation' => 'Alright. I found your room.', 'trigger_goal' => 'hotel-name', 'priority' => 100],
                        ],
                        'goals' => [
                            ['slug' => 'hotel-name', 'label' => 'Give your name', 'intent' => 'The learner says their name for check-in.', 'example' => 'Mano vardas Jonas Petrauskas.', 'next' => 'pusryciai'],
                        ],
                    ],
                    [
                        'slug' => 'pusryciai',
                        'setting' => 'The check-in is almost finished.',
                        'lines' => [
                            ['target_text' => 'Ar turite klausimų?', 'support_translation' => 'Do you have questions?'],
                            ['target_text' => 'Štai jūsų raktas. Ko dar reikės?', 'support_translation' => 'Here is your key. What else do you need?'],
                            ['target_text' => 'Pusryčiai yra nuo septintos iki dešimtos.', 'support_translation' => 'Breakfast is from seven to ten.', 'trigger_goal' => 'hotel-breakfast', 'priority' => 100],
                            ['target_text' => 'Pusryčiai prasideda septintą valandą.', 'support_translation' => 'Breakfast starts at seven o’clock.', 'trigger_goal' => 'hotel-breakfast', 'priority' => 100],
                            ['target_text' => 'Laba naktis. Gero poilsio!', 'support_translation' => 'Good night. Have a good rest!', 'trigger_goal' => 'hotel-thanks', 'priority' => 100],
                            ['target_text' => 'Prašom. Gražaus vakaro!', 'support_translation' => 'You are welcome. Have a nice evening!', 'trigger_goal' => 'hotel-thanks', 'priority' => 100],
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
                            ['target_text' => 'Laba diena. Kur važiuosite?', 'support_translation' => 'Good day. Where are you going?'],
                            ['target_text' => 'Sveiki. Reikia bilieto?', 'support_translation' => 'Hello. Do you need a ticket?'],
                            ['target_text' => 'Gerai, vienas bilietas į centrą.', 'support_translation' => 'Alright, one ticket to the center.', 'trigger_goal' => 'bus-center', 'priority' => 100],
                            ['target_text' => 'Žinoma, bilietas į centrą.', 'support_translation' => 'Of course, a ticket to the center.', 'trigger_goal' => 'bus-center', 'priority' => 100],
                            ['target_text' => 'Žinoma, vienas bilietas į stotį.', 'support_translation' => 'Of course, one ticket to the station.', 'trigger_goal' => 'bus-station', 'priority' => 100],
                            ['target_text' => 'Gerai, bilietas į stotį.', 'support_translation' => 'Alright, a ticket to the station.', 'trigger_goal' => 'bus-station', 'priority' => 100],
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
                            ['target_text' => 'Bilietas kainuoja vieną eurą.', 'support_translation' => 'The ticket costs one euro.'],
                            ['target_text' => 'Bus vienas euras.', 'support_translation' => 'That will be one euro.'],
                            ['target_text' => 'Ačiū. Štai jūsų bilietas.', 'support_translation' => 'Thank you. Here is your ticket.', 'trigger_goal' => 'bus-pay', 'priority' => 100],
                            ['target_text' => 'Puiku. Štai bilietas.', 'support_translation' => 'Great. Here is the ticket.', 'trigger_goal' => 'bus-pay', 'priority' => 100],
                            ['target_text' => 'Taip, galite mokėti kortele.', 'support_translation' => 'Yes, you can pay by card.', 'trigger_goal' => 'bus-card-question', 'priority' => 100],
                            ['target_text' => 'Taip, kortelė tinka.', 'support_translation' => 'Yes, card is fine.', 'trigger_goal' => 'bus-card-question', 'priority' => 100],
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
                            ['target_text' => 'Atsisėskite, prašau.', 'support_translation' => 'Please sit down.'],
                            ['target_text' => 'Važiuosime apie dešimt minučių.', 'support_translation' => 'We will ride for about ten minutes.'],
                            ['target_text' => 'Jūsų stotelė bus netrukus.', 'support_translation' => 'Your stop will be soon.', 'trigger_goal' => 'bus-stop', 'priority' => 100],
                            ['target_text' => 'Pasakysiu, kada reikės išlipti.', 'support_translation' => 'I will tell you when you need to get off.', 'trigger_goal' => 'bus-stop', 'priority' => 100],
                            ['target_text' => 'Nėra už ką. Geros kelionės!', 'support_translation' => 'You are welcome. Have a good trip!', 'trigger_goal' => 'bus-thanks', 'priority' => 100],
                            ['target_text' => 'Prašom. Geros dienos!', 'support_translation' => 'You are welcome. Have a nice day!', 'trigger_goal' => 'bus-thanks', 'priority' => 100],
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
                            ['target_text' => 'Laba diena. Ar jums reikia pagalbos?', 'support_translation' => 'Good day. Do you need help?'],
                            ['target_text' => 'Sveiki. Ar pasiklydote?', 'support_translation' => 'Hello. Are you lost?'],
                            ['target_text' => 'Žinoma, padėsiu.', 'support_translation' => 'Of course, I will help.', 'trigger_goal' => 'city-help', 'priority' => 100],
                            ['target_text' => 'Taip, galiu padėti.', 'support_translation' => 'Yes, I can help.', 'trigger_goal' => 'city-help', 'priority' => 100],
                            ['target_text' => 'Gerai. Kur norite eiti?', 'support_translation' => 'Alright. Where do you want to go?', 'trigger_goal' => 'city-lost', 'priority' => 100],
                            ['target_text' => 'Suprantu. Kokios vietos ieškote?', 'support_translation' => 'I understand. What place are you looking for?', 'trigger_goal' => 'city-lost', 'priority' => 100],
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
                            ['target_text' => 'Ko jūs ieškote?', 'support_translation' => 'What are you looking for?'],
                            ['target_text' => 'Kokia vieta jums reikalinga?', 'support_translation' => 'What place do you need?'],
                            ['target_text' => 'Stotis yra tiesiai ir tada į kairę.', 'support_translation' => 'The station is straight ahead and then left.', 'trigger_goal' => 'city-station', 'priority' => 100],
                            ['target_text' => 'Eikite tiesiai. Stotis bus kairėje.', 'support_translation' => 'Go straight. The station will be on the left.', 'trigger_goal' => 'city-station', 'priority' => 100],
                            ['target_text' => 'Vaistinė yra šalia parduotuvės.', 'support_translation' => 'The pharmacy is next to the shop.', 'trigger_goal' => 'city-pharmacy', 'priority' => 100],
                            ['target_text' => 'Vaistinė yra netoli, dešinėje.', 'support_translation' => 'The pharmacy is nearby, on the right.', 'trigger_goal' => 'city-pharmacy', 'priority' => 100],
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
                            ['target_text' => 'Ar aišku?', 'support_translation' => 'Is it clear?'],
                            ['target_text' => 'Eikite lėtai. Tai netoli.', 'support_translation' => 'Walk slowly. It is not far.'],
                            ['target_text' => 'Taip, eikite tiesiai.', 'support_translation' => 'Yes, go straight.', 'trigger_goal' => 'city-straight', 'priority' => 100],
                            ['target_text' => 'Taip, eikite tiesiai toliau.', 'support_translation' => 'Yes, keep going straight.', 'trigger_goal' => 'city-straight', 'priority' => 100],
                            ['target_text' => 'Prašom. Sėkmės!', 'support_translation' => 'You are welcome. Good luck!', 'trigger_goal' => 'city-thanks', 'priority' => 100],
                            ['target_text' => 'Nėra už ką. Iki pasimatymo!', 'support_translation' => 'You are welcome. See you!', 'trigger_goal' => 'city-thanks', 'priority' => 100],
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
                            ['target_text' => 'Laba diena. Kas jums yra?', 'support_translation' => 'Good day. What is the matter?'],
                            ['target_text' => 'Sveiki. Kaip jaučiatės?', 'support_translation' => 'Hello. How do you feel?'],
                            ['target_text' => 'Suprantu. Ar turite temperatūros?', 'support_translation' => 'I understand. Do you have a fever?', 'trigger_goal' => 'doctor-headache', 'priority' => 100],
                            ['target_text' => 'Gerai. Ar labai skauda?', 'support_translation' => 'Alright. Does it hurt a lot?', 'trigger_goal' => 'doctor-headache', 'priority' => 100],
                            ['target_text' => 'Gerai. Ar skauda stipriai?', 'support_translation' => 'Alright. Does it hurt a lot?', 'trigger_goal' => 'doctor-throat', 'priority' => 100],
                            ['target_text' => 'Suprantu. Ar dar kosėjate?', 'support_translation' => 'I understand. Are you also coughing?', 'trigger_goal' => 'doctor-throat', 'priority' => 100],
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
                            ['target_text' => 'Ar turite temperatūros?', 'support_translation' => 'Do you have a fever?'],
                            ['target_text' => 'Ar kosėjate?', 'support_translation' => 'Are you coughing?'],
                            ['target_text' => 'Gerai, užrašysiu.', 'support_translation' => 'Alright, I will write it down.', 'trigger_goal' => 'doctor-yes-fever', 'priority' => 100],
                            ['target_text' => 'Gerai, turite temperatūros.', 'support_translation' => 'Alright, you have a fever.', 'trigger_goal' => 'doctor-yes-fever', 'priority' => 100],
                            ['target_text' => 'Gerai. Tai skamba nesunkiai.', 'support_translation' => 'Alright. That sounds mild.', 'trigger_goal' => 'doctor-no-fever', 'priority' => 100],
                            ['target_text' => 'Gerai, temperatūros nėra.', 'support_translation' => 'Alright, there is no fever.', 'trigger_goal' => 'doctor-no-fever', 'priority' => 100],
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
                            ['target_text' => 'Gerkite vandens ir ilsėkitės.', 'support_translation' => 'Drink water and rest.'],
                            ['target_text' => 'Štai receptas. Ar turite klausimų?', 'support_translation' => 'Here is a prescription. Do you have questions?'],
                            ['target_text' => 'Vaistus vartokite du kartus per dieną.', 'support_translation' => 'Take the medicine twice a day.', 'trigger_goal' => 'doctor-medicine', 'priority' => 100],
                            ['target_text' => 'Gerkite vaistus ryte ir vakare.', 'support_translation' => 'Take the medicine in the morning and evening.', 'trigger_goal' => 'doctor-medicine', 'priority' => 100],
                            ['target_text' => 'Prašom. Linkiu pasveikti!', 'support_translation' => 'You are welcome. I hope you get better!', 'trigger_goal' => 'doctor-thanks', 'priority' => 100],
                            ['target_text' => 'Nėra už ką. Gero poilsio!', 'support_translation' => 'You are welcome. Have a good rest!', 'trigger_goal' => 'doctor-thanks', 'priority' => 100],
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
                            ['target_text' => 'Labas rytas, klase!', 'support_translation' => 'Good morning, class!'],
                            ['target_text' => 'Sveiki. Pradedame pamoką.', 'support_translation' => 'Hello. We are starting the lesson.'],
                            ['target_text' => 'Labas rytas. Prašau atsisėsti.', 'support_translation' => 'Good morning. Please sit down.', 'trigger_goal' => 'class-greet', 'priority' => 100],
                            ['target_text' => 'Labas rytas. Smagu jus matyti.', 'support_translation' => 'Good morning. Nice to see you.', 'trigger_goal' => 'class-greet', 'priority' => 100],
                            ['target_text' => 'Gerai. Atsiverskite knygą.', 'support_translation' => 'Alright. Open the book.', 'trigger_goal' => 'class-ready', 'priority' => 100],
                            ['target_text' => 'Puiku. Galime pradėti.', 'support_translation' => 'Great. We can start.', 'trigger_goal' => 'class-ready', 'priority' => 100],
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
                            ['target_text' => 'Pakartokite: ačiū.', 'support_translation' => 'Repeat: thank you.'],
                            ['target_text' => 'Dabar sakome: prašau.', 'support_translation' => 'Now we say: please.'],
                            ['target_text' => 'Nieko tokio. Pakartosiu lėčiau.', 'support_translation' => 'No problem. I will repeat more slowly.', 'trigger_goal' => 'class-not-understand', 'priority' => 100],
                            ['target_text' => 'Gerai. Pasakysiu dar kartą.', 'support_translation' => 'Alright. I will say it again.', 'trigger_goal' => 'class-not-understand', 'priority' => 100],
                            ['target_text' => 'Žinoma. Sakau dar kartą: ačiū.', 'support_translation' => 'Of course. I say again: thank you.', 'trigger_goal' => 'class-repeat', 'priority' => 100],
                            ['target_text' => 'Taip, pakartoju: ačiū.', 'support_translation' => 'Yes, I repeat: thank you.', 'trigger_goal' => 'class-repeat', 'priority' => 100],
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
                            ['target_text' => 'Labai gerai. Pamoka baigta.', 'support_translation' => 'Very good. The lesson is finished.'],
                            ['target_text' => 'Šaunu. Iki kitos pamokos.', 'support_translation' => 'Great. Until the next lesson.'],
                            ['target_text' => 'Ačiū jums. Iki pasimatymo!', 'support_translation' => 'Thank you. See you!', 'trigger_goal' => 'class-thanks', 'priority' => 100],
                            ['target_text' => 'Prašom. Puikiai dirbote.', 'support_translation' => 'You are welcome. You worked very well.', 'trigger_goal' => 'class-thanks', 'priority' => 100],
                            ['target_text' => 'Iki rytojaus!', 'support_translation' => 'See you tomorrow!', 'trigger_goal' => 'class-goodbye', 'priority' => 100],
                            ['target_text' => 'Viso gero. Iki kitos pamokos!', 'support_translation' => 'Goodbye. Until the next lesson!', 'trigger_goal' => 'class-goodbye', 'priority' => 100],
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
