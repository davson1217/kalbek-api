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

class PharmacyVisitScenarioSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $lithuanian = Language::query()->where('code', 'lt')->firstOrFail();
            $character = Character::query()->where('slug', 'rasa')->firstOrFail();

            $scenario = Scenario::query()->updateOrCreate(
                ['slug' => 'pharmacy-visit'],
                [
                    'language_id' => $lithuanian->id,
                    'character_id' => $character->id,
                    'title' => 'Vaistinėje',
                    'subtitle' => 'At the pharmacy',
                    'description' => 'Greet the pharmacist, say a simple health problem, ask about medicine, and pay.',
                    'emoji' => '💊',
                    'tone' => 'mint',
                    'cefr_level' => 'a1',
                    'start_scene_slug' => 'greeting',
                    'status' => ContentStatus::Published,
                    'is_free' => false,
                    'sort_order' => 50,
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
        $medicine = [
            ['target_text' => 'Tabletės nuo galvos skausmo', 'support_translation' => 'Headache tablets', 'price' => '6,00 €'],
            ['target_text' => 'Pastilės gerklei', 'support_translation' => 'Throat lozenges', 'price' => '4,50 €'],
            ['target_text' => 'Vitaminas C', 'support_translation' => 'Vitamin C', 'price' => '5,00 €'],
        ];

        return [
            [
                'slug' => 'greeting',
                'setting' => 'The learner enters a pharmacy. Rasa is behind the counter.',
                'lines' => [
                    ['target_text' => 'Laba diena. Kuo galiu jums padėti?', 'support_translation' => 'Good day. How can I help you?'],
                    ['target_text' => 'Sveiki. Ko ieškote?', 'support_translation' => 'Hello. What are you looking for?'],
                    ['target_text' => 'Laba diena. Prašom, klausau jūsų.', 'support_translation' => 'Good day. Please, I am listening.'],
                    ['target_text' => 'Žinoma. Kas jums yra?', 'support_translation' => 'Of course. What is the matter?', 'trigger_goal' => 'ask-for-help', 'priority' => 100],
                    ['target_text' => 'Gerai. Kaip galiu padėti?', 'support_translation' => 'Alright. How can I help?', 'trigger_goal' => 'ask-for-help', 'priority' => 100],
                    ['target_text' => 'Laba diena. Kaip jaučiatės?', 'support_translation' => 'Good day. How do you feel?', 'trigger_goal' => 'say-hello', 'priority' => 100],
                    ['target_text' => 'Sveiki. Kas atsitiko?', 'support_translation' => 'Hello. What happened?', 'trigger_goal' => 'say-hello', 'priority' => 100],
                ],
                'goals' => [
                    ['slug' => 'ask-for-help', 'label' => 'Ask for help', 'intent' => 'The learner greets the pharmacist and says they need help.', 'example' => 'Laba diena. Man reikia pagalbos.', 'next' => 'symptoms'],
                    ['slug' => 'say-hello', 'label' => 'Greet the pharmacist', 'intent' => 'The learner greets the pharmacist politely.', 'example' => 'Laba diena.', 'next' => 'symptoms'],
                ],
            ],
            [
                'slug' => 'symptoms',
                'setting' => 'Rasa asks what is wrong.',
                'lines' => [
                    ['target_text' => 'Kas jums yra?', 'support_translation' => 'What is the matter?'],
                    ['target_text' => 'Ką jums skauda?', 'support_translation' => 'What hurts?'],
                    ['target_text' => 'Prašau pasakyti, kaip jaučiatės.', 'support_translation' => 'Please say how you feel.'],
                    ['target_text' => 'Suprantu. Rekomenduoju tabletes nuo galvos skausmo.', 'support_translation' => 'I understand. I recommend headache tablets.', 'trigger_goal' => 'headache', 'priority' => 100],
                    ['target_text' => 'Gerai. Nuo galvos skausmo tinka šios tabletės.', 'support_translation' => 'Alright. These tablets are good for a headache.', 'trigger_goal' => 'headache', 'priority' => 100],
                    ['target_text' => 'Suprantu. Rekomenduoju pastiles gerklei.', 'support_translation' => 'I understand. I recommend throat lozenges.', 'trigger_goal' => 'sore-throat', 'priority' => 100],
                    ['target_text' => 'Gerai. Šios pastilės yra gerklei.', 'support_translation' => 'Alright. These lozenges are for the throat.', 'trigger_goal' => 'sore-throat', 'priority' => 100],
                    ['target_text' => 'Gerai. Rekomenduoju vitaminą C.', 'support_translation' => 'Alright. I recommend vitamin C.', 'trigger_goal' => 'cold', 'priority' => 100],
                    ['target_text' => 'Suprantu. Galite vartoti vitaminą C.', 'support_translation' => 'I understand. You can take vitamin C.', 'trigger_goal' => 'cold', 'priority' => 100],
                ],
                'goals' => [
                    ['slug' => 'headache', 'label' => 'Say your head hurts', 'intent' => 'The learner says they have a headache.', 'example' => 'Man skauda galvą.', 'next' => 'medicine'],
                    ['slug' => 'sore-throat', 'label' => 'Say your throat hurts', 'intent' => 'The learner says their throat hurts.', 'example' => 'Man skauda gerklę.', 'next' => 'medicine'],
                    ['slug' => 'cold', 'label' => 'Say you have a cold', 'intent' => 'The learner says they have a cold.', 'example' => 'Aš peršalau.', 'next' => 'medicine'],
                ],
            ],
            [
                'slug' => 'medicine',
                'setting' => 'Rasa shows a few simple medicines.',
                'lines' => [
                    ['target_text' => 'Štai galimi vaistai.', 'support_translation' => 'Here are possible medicines.'],
                    ['target_text' => 'Ar norite paklausti kainos ar kaip vartoti?', 'support_translation' => 'Do you want to ask the price or how to use it?'],
                    ['target_text' => 'Prašom. Galite paklausti apie kainą arba vartojimą.', 'support_translation' => 'Please. You can ask about the price or use.'],
                    ['target_text' => 'Šis vaistas kainuoja šešis eurus.', 'support_translation' => 'This medicine costs six euros.', 'trigger_goal' => 'ask-price', 'priority' => 100],
                    ['target_text' => 'Kaina yra šeši eurai.', 'support_translation' => 'The price is six euros.', 'trigger_goal' => 'ask-price', 'priority' => 100],
                    ['target_text' => 'Vartokite po vieną tabletę du kartus per dieną.', 'support_translation' => 'Take one tablet twice a day.', 'trigger_goal' => 'ask-how-to-use', 'priority' => 100],
                    ['target_text' => 'Gerkite vieną tabletę ryte ir vakare.', 'support_translation' => 'Take one tablet in the morning and evening.', 'trigger_goal' => 'ask-how-to-use', 'priority' => 100],
                    ['target_text' => 'Taip, norint nusipirkti, galite eiti prie kasos.', 'support_translation' => 'Yes, if you want to buy it, you can go to the checkout.', 'trigger_goal' => 'want-buy', 'priority' => 100],
                    ['target_text' => 'Gerai, galite pirkti prie kasos.', 'support_translation' => 'Alright, you can buy it at the checkout.', 'trigger_goal' => 'want-buy', 'priority' => 100],
                ],
                'props' => $medicine,
                'goals' => [
                    ['slug' => 'ask-price', 'label' => 'Ask the price', 'intent' => 'The learner asks how much the medicine costs.', 'example' => 'Kiek tai kainuoja?', 'next' => 'payment'],
                    ['slug' => 'ask-how-to-use', 'label' => 'Ask how to use it', 'intent' => 'The learner asks how to take or use the medicine.', 'example' => 'Kaip vartoti šį vaistą?', 'next' => 'payment'],
                    ['slug' => 'want-buy', 'label' => 'Say you want to buy it', 'intent' => 'The learner says they want to buy the medicine.', 'example' => 'Norėčiau nusipirkti.', 'next' => 'payment'],
                ],
            ],
            [
                'slug' => 'payment',
                'setting' => 'The learner is ready to pay.',
                'lines' => [
                    ['target_text' => 'Ar mokėsite kortele ar grynaisiais?', 'support_translation' => 'Will you pay by card or in cash?'],
                    ['target_text' => 'Prašom prie kasos.', 'support_translation' => 'Please come to the checkout.'],
                    ['target_text' => 'Kaip norėsite mokėti?', 'support_translation' => 'How would you like to pay?'],
                    ['target_text' => 'Ačiū. Mokėjimas kortele priimtas.', 'support_translation' => 'Thank you. Card payment accepted.', 'trigger_goal' => 'pay-card', 'priority' => 100],
                    ['target_text' => 'Puiku. Kortele apmokėta.', 'support_translation' => 'Great. Paid by card.', 'trigger_goal' => 'pay-card', 'priority' => 100],
                    ['target_text' => 'Ačiū. Štai jūsų grąža.', 'support_translation' => 'Thank you. Here is your change.', 'trigger_goal' => 'pay-cash', 'priority' => 100],
                    ['target_text' => 'Ačiū. Grynaisiais tinka.', 'support_translation' => 'Thank you. Cash is fine.', 'trigger_goal' => 'pay-cash', 'priority' => 100],
                    ['target_text' => 'Prašom. Linkiu greitai pasveikti!', 'support_translation' => 'You are welcome. I hope you get well soon!', 'trigger_goal' => 'say-goodbye', 'priority' => 100],
                    ['target_text' => 'Ačiū jums. Iki pasimatymo!', 'support_translation' => 'Thank you. See you!', 'trigger_goal' => 'say-goodbye', 'priority' => 100],
                ],
                'goals' => [
                    ['slug' => 'pay-card', 'label' => 'Pay by card', 'intent' => 'The learner says they will pay by card.', 'example' => 'Mokėsiu kortele.', 'next' => null],
                    ['slug' => 'pay-cash', 'label' => 'Pay in cash', 'intent' => 'The learner says they will pay in cash.', 'example' => 'Mokėsiu grynaisiais.', 'next' => null],
                    ['slug' => 'say-goodbye', 'label' => 'Thank and say goodbye', 'intent' => 'The learner thanks the pharmacist and says goodbye.', 'example' => 'Ačiū, viso gero.', 'next' => null],
                ],
            ],
        ];
    }
}
