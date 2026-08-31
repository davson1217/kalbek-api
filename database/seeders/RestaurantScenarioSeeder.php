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

class RestaurantScenarioSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $lithuanian = Language::query()->where('code', 'lt')->firstOrFail();
            $character = Character::query()->where('slug', 'rasa')->firstOrFail();

            $scenario = Scenario::query()->updateOrCreate(
                ['slug' => 'restoranas'],
                [
                    'language_id' => $lithuanian->id,
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
                    'target_text' => $line['target_text'],
                    'support_translation' => $line['support_translation'],
                    'cefr_level' => 'a1',
                    'priority' => $line['priority'] ?? 0,
                    'sort_order' => ($index + 1) * 10,
                ]);
            }

            foreach ($sceneData['props'] ?? [] as $index => $prop) {
                $scene->props()->create([
                    'type' => 'menu_item',
                    'target_text' => $prop['target_text'],
                    'support_translation' => $prop['support_translation'],
                    'price' => $prop['price'],
                    'sort_order' => ($index + 1) * 10,
                ]);
            }
        }
    }

    private function scenes(): array
    {
        $menu = [
            ['target_text' => 'Sriuba', 'support_translation' => 'Soup', 'price' => '4,00 €'],
            ['target_text' => 'Salotos', 'support_translation' => 'Salad', 'price' => '5,00 €'],
            ['target_text' => 'Vištiena', 'support_translation' => 'Chicken', 'price' => '8,00 €'],
            ['target_text' => 'Vanduo', 'support_translation' => 'Water', 'price' => '1,50 €'],
            ['target_text' => 'Arbata', 'support_translation' => 'Tea', 'price' => '2,00 €'],
        ];

        return [
            [
                'slug' => 'atvykimas',
                'setting' => 'The learner enters a small restaurant. Rasa greets them at the door.',
                'lines' => [
                    ['target_text' => 'Laba diena! Kuo galiu jums padėti?', 'support_translation' => 'Good day! How can I help you?'],
                    ['target_text' => 'Sveiki atvykę. Ar norite staliuko?', 'support_translation' => 'Welcome. Would you like a table?'],
                    ['target_text' => 'Žinoma. Turime laisvą staliuką.', 'support_translation' => 'Of course. We have a free table.', 'trigger_goal' => 'ask-table', 'priority' => 100],
                    ['target_text' => 'Taip, prašom. Staliukas yra laisvas.', 'support_translation' => 'Yes, please. A table is free.', 'trigger_goal' => 'ask-table', 'priority' => 100],
                    ['target_text' => 'Gerai, staliukas dviem.', 'support_translation' => 'Alright, a table for two.', 'trigger_goal' => 'ask-table-two', 'priority' => 100],
                    ['target_text' => 'Žinoma, turime staliuką dviem.', 'support_translation' => 'Of course, we have a table for two.', 'trigger_goal' => 'ask-table-two', 'priority' => 100],
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
                    ['target_text' => 'Prašom, sėskitės čia.', 'support_translation' => 'Please, sit here.'],
                    ['target_text' => 'Šis staliukas laisvas.', 'support_translation' => 'This table is free.'],
                    ['target_text' => 'Čia galite atsisėsti.', 'support_translation' => 'You can sit here.'],
                    ['target_text' => 'Prašom. Štai meniu.', 'support_translation' => 'Here you are. Here is the menu.', 'trigger_goal' => 'thank-menu', 'priority' => 100],
                    ['target_text' => 'Nėra už ką. Štai meniu.', 'support_translation' => 'You are welcome. Here is the menu.', 'trigger_goal' => 'thank-menu', 'priority' => 100],
                    ['target_text' => 'Taip, štai meniu.', 'support_translation' => 'Yes, here is the menu.', 'trigger_goal' => 'ask-menu', 'priority' => 100],
                    ['target_text' => 'Žinoma. Prašom, meniu.', 'support_translation' => 'Of course. Here is the menu.', 'trigger_goal' => 'ask-menu', 'priority' => 100],
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
                    ['target_text' => 'Ko norėsite valgyti?', 'support_translation' => 'What would you like to eat?'],
                    ['target_text' => 'Turime sriubos, salotų ir vištienos.', 'support_translation' => 'We have soup, salad, and chicken.'],
                    ['target_text' => 'Ką pasirinksite iš meniu?', 'support_translation' => 'What will you choose from the menu?'],
                    ['target_text' => 'Gerai, viena sriuba.', 'support_translation' => 'Alright, one soup.', 'trigger_goal' => 'order-soup', 'priority' => 100],
                    ['target_text' => 'Žinoma, sriuba.', 'support_translation' => 'Of course, soup.', 'trigger_goal' => 'order-soup', 'priority' => 100],
                    ['target_text' => 'Gerai, salotos.', 'support_translation' => 'Alright, salad.', 'trigger_goal' => 'order-salad', 'priority' => 100],
                    ['target_text' => 'Puiku, atnešiu salotų.', 'support_translation' => 'Great, I will bring salad.', 'trigger_goal' => 'order-salad', 'priority' => 100],
                    ['target_text' => 'Puiku, vištiena.', 'support_translation' => 'Great, chicken.', 'trigger_goal' => 'order-chicken', 'priority' => 100],
                    ['target_text' => 'Gerai, viena vištiena.', 'support_translation' => 'Alright, one chicken.', 'trigger_goal' => 'order-chicken', 'priority' => 100],
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
                    ['target_text' => 'Ką gersite?', 'support_translation' => 'What will you drink?'],
                    ['target_text' => 'Ar norėsite vandens ar arbatos?', 'support_translation' => 'Would you like water or tea?'],
                    ['target_text' => 'Kokio gėrimo norėsite?', 'support_translation' => 'What drink would you like?'],
                    ['target_text' => 'Gerai, atnešiu vandens.', 'support_translation' => 'Alright, I will bring water.', 'trigger_goal' => 'order-water', 'priority' => 100],
                    ['target_text' => 'Žinoma, vandens.', 'support_translation' => 'Of course, water.', 'trigger_goal' => 'order-water', 'priority' => 100],
                    ['target_text' => 'Gerai, viena arbata.', 'support_translation' => 'Alright, one tea.', 'trigger_goal' => 'order-tea', 'priority' => 100],
                    ['target_text' => 'Puiku, arbata.', 'support_translation' => 'Great, tea.', 'trigger_goal' => 'order-tea', 'priority' => 100],
                    ['target_text' => 'Gerai, be gėrimo.', 'support_translation' => 'Alright, no drink.', 'trigger_goal' => 'no-drink', 'priority' => 100],
                    ['target_text' => 'Supratau, gėrimo nereikia.', 'support_translation' => 'I understand, no drink is needed.', 'trigger_goal' => 'no-drink', 'priority' => 100],
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
                    ['target_text' => 'Štai sąskaita. Kaip mokėsite?', 'support_translation' => 'Here is the bill. How will you pay?'],
                    ['target_text' => 'Iš viso dešimt eurų.', 'support_translation' => 'Ten euros in total.'],
                    ['target_text' => 'Ar mokėsite kortele?', 'support_translation' => 'Will you pay by card?'],
                    ['target_text' => 'Ačiū. Mokėjimas kortele priimtas.', 'support_translation' => 'Thank you. Card payment accepted.', 'trigger_goal' => 'pay-card', 'priority' => 100],
                    ['target_text' => 'Puiku. Kortele apmokėta.', 'support_translation' => 'Great. Paid by card.', 'trigger_goal' => 'pay-card', 'priority' => 100],
                    ['target_text' => 'Ačiū. Geros dienos!', 'support_translation' => 'Thank you. Have a good day!', 'trigger_goal' => 'thank-goodbye', 'priority' => 100],
                    ['target_text' => 'Prašom. Iki pasimatymo!', 'support_translation' => 'You are welcome. See you!', 'trigger_goal' => 'thank-goodbye', 'priority' => 100],
                ],
                'goals' => [
                    ['slug' => 'pay-card', 'label' => 'Pay by card', 'intent' => 'The learner says they will pay by card.', 'example' => 'Mokėsiu kortele.', 'next' => null],
                    ['slug' => 'thank-goodbye', 'label' => 'Thank and say goodbye', 'intent' => 'The learner thanks the waitress and says goodbye.', 'example' => 'Ačiū, viso gero.', 'next' => null],
                ],
            ],
        ];
    }
}
