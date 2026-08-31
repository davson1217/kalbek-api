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
                    'description' => 'Greet the waitress, ask for a table, order simple food and drink, and pay.',
                    'emoji' => '🍽️',
                    'tone' => 'primary',
                    'cefr_level' => 'a1',
                    'start_scene_slug' => 'atvykimas',
                    'status' => ContentStatus::Published,
                    'sort_order' => 40,
                    'published_at' => now(),
                ],
            );

            $scenario->scenes()
                ->whereNotIn('slug', collect($this->scenes())->pluck('slug')->all())
                ->delete();

            $scenes = $this->upsertScenes($scenario);
            $this->replaceSceneContent($scenes);
        });
    }

    /** @return Collection<string, Scene> */
    private function upsertScenes(Scenario $scenario): Collection
    {
        foreach ($this->scenes() as $index => $scene) {
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

    /** @param Collection<string, Scene> $scenes */
    private function replaceSceneContent(Collection $scenes): void
    {
        foreach ($this->scenes() as $sceneData) {
            /** @var Scene $scene */
            $scene = $scenes->get($sceneData['slug']);
            $scene->npcLines()->delete();
            $scene->props()->delete();

            foreach ($sceneData['goals'] as $index => $goal) {
                $scene->goals()->updateOrCreate(
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

            $scene->goals()
                ->whereNotIn('slug', collect($sceneData['goals'])->pluck('slug')->all())
                ->delete();
        }

        $goals = Goal::query()
            ->whereIn('scene_id', $scenes->pluck('id'))
            ->get()
            ->keyBy('slug');

        foreach ($this->scenes() as $sceneData) {
            /** @var Scene $scene */
            $scene = $scenes->get($sceneData['slug']);

            foreach ($sceneData['lines'] as $index => $line) {
                $scene->npcLines()->create([
                    'trigger_goal_id' => isset($line['trigger_goal']) ? $goals->get($line['trigger_goal'])?->id : null,
                    'lt' => $line['lt'],
                    'en' => $line['en'],
                    'cefr_level' => 'a1',
                    'priority' => $line['priority'] ?? 0,
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
        }
    }

    private function scenes(): array
    {
        $menu = [
            ['lt' => 'Sriuba', 'en' => 'Soup', 'price' => '4,00 €'],
            ['lt' => 'Salotos', 'en' => 'Salad', 'price' => '5,00 €'],
            ['lt' => 'Vištiena', 'en' => 'Chicken', 'price' => '8,00 €'],
            ['lt' => 'Vanduo', 'en' => 'Water', 'price' => '1,50 €'],
            ['lt' => 'Arbata', 'en' => 'Tea', 'price' => '2,00 €'],
        ];

        return [
            [
                'slug' => 'atvykimas',
                'setting' => 'The learner enters a small restaurant. Rasa greets them at the door.',
                'lines' => [
                    ['lt' => 'Laba diena! Kuo galiu jums padėti?', 'en' => 'Good day! How can I help you?'],
                    ['lt' => 'Sveiki atvykę. Ar norite staliuko?', 'en' => 'Welcome. Would you like a table?'],
                    ['lt' => 'Žinoma. Turime laisvą staliuką.', 'en' => 'Of course. We have a free table.', 'trigger_goal' => 'ask-table', 'priority' => 100],
                    ['lt' => 'Taip, prašom. Staliukas yra laisvas.', 'en' => 'Yes, please. A table is free.', 'trigger_goal' => 'ask-table', 'priority' => 100],
                    ['lt' => 'Gerai, staliukas dviem.', 'en' => 'Alright, a table for two.', 'trigger_goal' => 'ask-table-two', 'priority' => 100],
                    ['lt' => 'Žinoma, turime staliuką dviem.', 'en' => 'Of course, we have a table for two.', 'trigger_goal' => 'ask-table-two', 'priority' => 100],
                ],
                'goals' => [
                    ['slug' => 'ask-table', 'label' => 'Ask for a table', 'intent' => 'The learner greets the waitress and asks for a table.', 'example' => 'Laba diena. Norėčiau staliuko, prašau.', 'next' => 'sodinimas'],
                    ['slug' => 'ask-table-two', 'label' => 'Ask for a table for two', 'intent' => 'The learner asks for a table for two people.', 'example' => 'Norėčiau staliuko dviem, prašau.', 'next' => 'sodinimas'],
                ],
            ],
            [
                'slug' => 'sodinimas',
                'setting' => 'Rasa shows the learner a table.',
                'lines' => [
                    ['lt' => 'Prašom, sėskitės čia.', 'en' => 'Please, sit here.'],
                    ['lt' => 'Šis staliukas laisvas.', 'en' => 'This table is free.'],
                    ['lt' => 'Čia galite atsisėsti.', 'en' => 'You can sit here.'],
                    ['lt' => 'Prašom. Štai meniu.', 'en' => 'Here you are. Here is the menu.', 'trigger_goal' => 'thank-menu', 'priority' => 100],
                    ['lt' => 'Nėra už ką. Štai meniu.', 'en' => 'You are welcome. Here is the menu.', 'trigger_goal' => 'thank-menu', 'priority' => 100],
                    ['lt' => 'Taip, štai meniu.', 'en' => 'Yes, here is the menu.', 'trigger_goal' => 'ask-menu', 'priority' => 100],
                    ['lt' => 'Žinoma. Prašom, meniu.', 'en' => 'Of course. Here is the menu.', 'trigger_goal' => 'ask-menu', 'priority' => 100],
                ],
                'goals' => [
                    ['slug' => 'thank-menu', 'label' => 'Thank her', 'intent' => 'The learner thanks the waitress.', 'example' => 'Ačiū.', 'next' => 'meniu'],
                    ['slug' => 'ask-menu', 'label' => 'Ask for the menu', 'intent' => 'The learner asks for the menu.', 'example' => 'Ar galiu gauti meniu?', 'next' => 'meniu'],
                ],
            ],
            [
                'slug' => 'meniu',
                'setting' => 'The learner looks at a short menu.',
                'lines' => [
                    ['lt' => 'Ko norėsite valgyti?', 'en' => 'What would you like to eat?'],
                    ['lt' => 'Turime sriubos, salotų ir vištienos.', 'en' => 'We have soup, salad, and chicken.'],
                    ['lt' => 'Ką pasirinksite iš meniu?', 'en' => 'What will you choose from the menu?'],
                    ['lt' => 'Gerai, viena sriuba.', 'en' => 'Alright, one soup.', 'trigger_goal' => 'order-soup', 'priority' => 100],
                    ['lt' => 'Žinoma, sriuba.', 'en' => 'Of course, soup.', 'trigger_goal' => 'order-soup', 'priority' => 100],
                    ['lt' => 'Gerai, salotos.', 'en' => 'Alright, salad.', 'trigger_goal' => 'order-salad', 'priority' => 100],
                    ['lt' => 'Puiku, atnešiu salotų.', 'en' => 'Great, I will bring salad.', 'trigger_goal' => 'order-salad', 'priority' => 100],
                    ['lt' => 'Puiku, vištiena.', 'en' => 'Great, chicken.', 'trigger_goal' => 'order-chicken', 'priority' => 100],
                    ['lt' => 'Gerai, viena vištiena.', 'en' => 'Alright, one chicken.', 'trigger_goal' => 'order-chicken', 'priority' => 100],
                ],
                'props' => $menu,
                'goals' => [
                    ['slug' => 'order-soup', 'label' => 'Order soup', 'intent' => 'The learner orders soup politely.', 'example' => 'Norėčiau sriubos, prašau.', 'next' => 'gerimas'],
                    ['slug' => 'order-salad', 'label' => 'Order salad', 'intent' => 'The learner orders salad politely.', 'example' => 'Norėčiau salotų, prašau.', 'next' => 'gerimas'],
                    ['slug' => 'order-chicken', 'label' => 'Order chicken', 'intent' => 'The learner orders chicken politely.', 'example' => 'Norėčiau vištienos, prašau.', 'next' => 'gerimas'],
                ],
            ],
            [
                'slug' => 'gerimas',
                'setting' => 'Rasa asks about a drink.',
                'lines' => [
                    ['lt' => 'Ką gersite?', 'en' => 'What will you drink?'],
                    ['lt' => 'Ar norėsite vandens ar arbatos?', 'en' => 'Would you like water or tea?'],
                    ['lt' => 'Kokio gėrimo norėsite?', 'en' => 'What drink would you like?'],
                    ['lt' => 'Gerai, atnešiu vandens.', 'en' => 'Alright, I will bring water.', 'trigger_goal' => 'order-water', 'priority' => 100],
                    ['lt' => 'Žinoma, vandens.', 'en' => 'Of course, water.', 'trigger_goal' => 'order-water', 'priority' => 100],
                    ['lt' => 'Gerai, viena arbata.', 'en' => 'Alright, one tea.', 'trigger_goal' => 'order-tea', 'priority' => 100],
                    ['lt' => 'Puiku, arbata.', 'en' => 'Great, tea.', 'trigger_goal' => 'order-tea', 'priority' => 100],
                    ['lt' => 'Gerai, be gėrimo.', 'en' => 'Alright, no drink.', 'trigger_goal' => 'no-drink', 'priority' => 100],
                    ['lt' => 'Supratau, gėrimo nereikia.', 'en' => 'I understand, no drink is needed.', 'trigger_goal' => 'no-drink', 'priority' => 100],
                ],
                'goals' => [
                    ['slug' => 'order-water', 'label' => 'Order water', 'intent' => 'The learner orders water.', 'example' => 'Vandens, prašau.', 'next' => 'mokejimas'],
                    ['slug' => 'order-tea', 'label' => 'Order tea', 'intent' => 'The learner orders tea.', 'example' => 'Arbatos, prašau.', 'next' => 'mokejimas'],
                    ['slug' => 'no-drink', 'label' => 'Say no drink', 'intent' => 'The learner says they do not want a drink.', 'example' => 'Nieko negersiu, ačiū.', 'next' => 'mokejimas'],
                ],
            ],
            [
                'slug' => 'mokejimas',
                'setting' => 'The meal is finished. Rasa brings the bill.',
                'lines' => [
                    ['lt' => 'Štai sąskaita. Kaip mokėsite?', 'en' => 'Here is the bill. How will you pay?'],
                    ['lt' => 'Iš viso dešimt eurų.', 'en' => 'Ten euros in total.'],
                    ['lt' => 'Ar mokėsite kortele?', 'en' => 'Will you pay by card?'],
                    ['lt' => 'Ačiū. Mokėjimas kortele priimtas.', 'en' => 'Thank you. Card payment accepted.', 'trigger_goal' => 'pay-card', 'priority' => 100],
                    ['lt' => 'Puiku. Kortele apmokėta.', 'en' => 'Great. Paid by card.', 'trigger_goal' => 'pay-card', 'priority' => 100],
                    ['lt' => 'Ačiū. Geros dienos!', 'en' => 'Thank you. Have a good day!', 'trigger_goal' => 'thank-goodbye', 'priority' => 100],
                    ['lt' => 'Prašom. Iki pasimatymo!', 'en' => 'You are welcome. See you!', 'trigger_goal' => 'thank-goodbye', 'priority' => 100],
                ],
                'goals' => [
                    ['slug' => 'pay-card', 'label' => 'Pay by card', 'intent' => 'The learner says they will pay by card.', 'example' => 'Mokėsiu kortele.', 'next' => null],
                    ['slug' => 'thank-goodbye', 'label' => 'Thank and say goodbye', 'intent' => 'The learner thanks the waitress and says goodbye.', 'example' => 'Ačiū, viso gero.', 'next' => null],
                ],
            ],
        ];
    }
}
