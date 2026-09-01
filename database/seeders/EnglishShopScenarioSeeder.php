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

class EnglishShopScenarioSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $english = Language::query()->where('code', 'en')->firstOrFail();
            $character = Character::query()->where('slug', 'emily')->firstOrFail();

            $scenario = Scenario::query()->updateOrCreate(
                ['slug' => 'at-the-shop'],
                [
                    'language_id' => $english->id,
                    'character_id' => $character->id,
                    'title' => 'At the shop',
                    'subtitle' => 'Ask for simple items',
                    'description' => 'Ask where an item is, ask the price, ask for a bag, and pay.',
                    'emoji' => '🛒',
                    'tone' => 'mint',
                    'cefr_level' => 'a1',
                    'start_scene_slug' => 'looking-for-items',
                    'status' => ContentStatus::Published,
                    'is_free' => true,
                    'sort_order' => 10,
                    'published_at' => now(),
                ],
            );

            $scenes = $this->upsertScenes($scenario, $this->scenes());
            $this->replaceSceneContent($scenes, $this->scenes());
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

    private function scenes(): array
    {
        return [
            [
                'slug' => 'looking-for-items',
                'setting' => 'Emily works in a small shop. The learner wants to find a basic item.',
                'lines' => [
                    ['target_text' => 'Hello! Can I help you?', 'support_translation' => 'Hello! Can I help you?'],
                    ['target_text' => 'Good morning. What are you looking for?', 'support_translation' => 'Good morning. What are you looking for?'],
                    ['target_text' => 'The milk is in the fridge on the left.', 'support_translation' => 'The milk is in the fridge on the left.', 'trigger_goal' => 'en-shop-milk', 'priority' => 100],
                    ['target_text' => 'Of course. The milk is on the left.', 'support_translation' => 'Of course. The milk is on the left.', 'trigger_goal' => 'en-shop-milk', 'priority' => 100],
                    ['target_text' => 'The bread is over there, near the window.', 'support_translation' => 'The bread is over there, near the window.', 'trigger_goal' => 'en-shop-bread', 'priority' => 100],
                    ['target_text' => 'Yes, the bread is near the window.', 'support_translation' => 'Yes, the bread is near the window.', 'trigger_goal' => 'en-shop-bread', 'priority' => 100],
                ],
                'goals' => [
                    ['slug' => 'en-shop-milk', 'label' => 'Ask where the milk is', 'intent' => 'The learner asks where the milk is.', 'example' => 'Where is the milk?', 'next' => 'price-and-bag'],
                    ['slug' => 'en-shop-bread', 'label' => 'Ask where the bread is', 'intent' => 'The learner asks where the bread is.', 'example' => 'Where is the bread?', 'next' => 'price-and-bag'],
                ],
                'props' => [
                    ['target_text' => 'Milk', 'support_translation' => 'Milk', 'price' => '€1.00'],
                    ['target_text' => 'Bread', 'support_translation' => 'Bread', 'price' => '€1.20'],
                    ['target_text' => 'Apples', 'support_translation' => 'Apples', 'price' => '€2.00'],
                    ['target_text' => 'Water', 'support_translation' => 'Water', 'price' => '€0.80'],
                ],
            ],
            [
                'slug' => 'price-and-bag',
                'setting' => 'The learner has found the item and needs simple checkout help.',
                'lines' => [
                    ['target_text' => 'Do you need anything else?', 'support_translation' => 'Do you need anything else?'],
                    ['target_text' => 'Did you find it? Great.', 'support_translation' => 'Did you find it? Great.'],
                    ['target_text' => 'It costs one euro.', 'support_translation' => 'It costs one euro.', 'trigger_goal' => 'en-shop-price', 'priority' => 100],
                    ['target_text' => 'The price is one euro.', 'support_translation' => 'The price is one euro.', 'trigger_goal' => 'en-shop-price', 'priority' => 100],
                    ['target_text' => 'Yes, we have bags.', 'support_translation' => 'Yes, we have bags.', 'trigger_goal' => 'en-shop-bag', 'priority' => 100],
                    ['target_text' => 'Of course. Here is a bag.', 'support_translation' => 'Of course. Here is a bag.', 'trigger_goal' => 'en-shop-bag', 'priority' => 100],
                ],
                'goals' => [
                    ['slug' => 'en-shop-price', 'label' => 'Ask the price', 'intent' => 'The learner asks how much it costs.', 'example' => 'How much does it cost?', 'next' => 'checkout'],
                    ['slug' => 'en-shop-bag', 'label' => 'Ask for a bag', 'intent' => 'The learner asks for a bag.', 'example' => 'Do you have a bag?', 'next' => 'checkout'],
                ],
            ],
            [
                'slug' => 'checkout',
                'setting' => 'The learner is at the checkout and is ready to pay.',
                'lines' => [
                    ['target_text' => 'Please come to the checkout. How will you pay?', 'support_translation' => 'Please come to the checkout. How will you pay?'],
                    ['target_text' => 'That is one euro in total.', 'support_translation' => 'That is one euro in total.'],
                    ['target_text' => 'Thank you. Your payment is accepted.', 'support_translation' => 'Thank you. Your payment is accepted.', 'trigger_goal' => 'en-shop-pay-card', 'priority' => 100],
                    ['target_text' => 'Great. Payment accepted.', 'support_translation' => 'Great. Payment accepted.', 'trigger_goal' => 'en-shop-pay-card', 'priority' => 100],
                    ['target_text' => 'You are welcome. See you!', 'support_translation' => 'You are welcome. See you!', 'trigger_goal' => 'en-shop-goodbye', 'priority' => 100],
                    ['target_text' => 'Thank you. Have a nice day!', 'support_translation' => 'Thank you. Have a nice day!', 'trigger_goal' => 'en-shop-goodbye', 'priority' => 100],
                ],
                'goals' => [
                    ['slug' => 'en-shop-pay-card', 'label' => 'Pay by card', 'intent' => 'The learner says they will pay by card.', 'example' => 'I will pay by card.', 'next' => null],
                    ['slug' => 'en-shop-goodbye', 'label' => 'Say goodbye', 'intent' => 'The learner thanks the shop assistant and says goodbye.', 'example' => 'Thank you, goodbye.', 'next' => null],
                ],
            ],
        ];
    }
}
