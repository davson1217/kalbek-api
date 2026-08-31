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

class PharmacyVisitScenarioSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $character = Character::query()->where('slug', 'rasa')->firstOrFail();

            $scenario = Scenario::query()->updateOrCreate(
                ['slug' => 'pharmacy-visit'],
                [
                    'character_id' => $character->id,
                    'title' => 'Vaistinėje',
                    'subtitle' => 'At the pharmacy',
                    'description' => 'Greet the pharmacist, say a simple health problem, ask about medicine, and pay.',
                    'emoji' => '💊',
                    'tone' => 'mint',
                    'cefr_level' => 'a1',
                    'start_scene_slug' => 'greeting',
                    'status' => ContentStatus::Published,
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
        $medicine = [
            ['lt' => 'Tabletės nuo galvos skausmo', 'en' => 'Headache tablets', 'price' => '6,00 €'],
            ['lt' => 'Pastilės gerklei', 'en' => 'Throat lozenges', 'price' => '4,50 €'],
            ['lt' => 'Vitaminas C', 'en' => 'Vitamin C', 'price' => '5,00 €'],
        ];

        return [
            [
                'slug' => 'greeting',
                'setting' => 'The learner enters a pharmacy. Rasa is behind the counter.',
                'lines' => [
                    ['lt' => 'Laba diena. Kuo galiu jums padėti?', 'en' => 'Good day. How can I help you?'],
                    ['lt' => 'Sveiki. Ko ieškote?', 'en' => 'Hello. What are you looking for?'],
                    ['lt' => 'Laba diena. Prašom, klausau jūsų.', 'en' => 'Good day. Please, I am listening.'],
                    ['lt' => 'Žinoma. Kas jums yra?', 'en' => 'Of course. What is the matter?', 'trigger_goal' => 'ask-for-help', 'priority' => 100],
                    ['lt' => 'Gerai. Kaip galiu padėti?', 'en' => 'Alright. How can I help?', 'trigger_goal' => 'ask-for-help', 'priority' => 100],
                    ['lt' => 'Laba diena. Kaip jaučiatės?', 'en' => 'Good day. How do you feel?', 'trigger_goal' => 'say-hello', 'priority' => 100],
                    ['lt' => 'Sveiki. Kas atsitiko?', 'en' => 'Hello. What happened?', 'trigger_goal' => 'say-hello', 'priority' => 100],
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
                    ['lt' => 'Kas jums yra?', 'en' => 'What is the matter?'],
                    ['lt' => 'Ką jums skauda?', 'en' => 'What hurts?'],
                    ['lt' => 'Prašau pasakyti, kaip jaučiatės.', 'en' => 'Please say how you feel.'],
                    ['lt' => 'Suprantu. Rekomenduoju tabletes nuo galvos skausmo.', 'en' => 'I understand. I recommend headache tablets.', 'trigger_goal' => 'headache', 'priority' => 100],
                    ['lt' => 'Gerai. Nuo galvos skausmo tinka šios tabletės.', 'en' => 'Alright. These tablets are good for a headache.', 'trigger_goal' => 'headache', 'priority' => 100],
                    ['lt' => 'Suprantu. Rekomenduoju pastiles gerklei.', 'en' => 'I understand. I recommend throat lozenges.', 'trigger_goal' => 'sore-throat', 'priority' => 100],
                    ['lt' => 'Gerai. Šios pastilės yra gerklei.', 'en' => 'Alright. These lozenges are for the throat.', 'trigger_goal' => 'sore-throat', 'priority' => 100],
                    ['lt' => 'Gerai. Rekomenduoju vitaminą C.', 'en' => 'Alright. I recommend vitamin C.', 'trigger_goal' => 'cold', 'priority' => 100],
                    ['lt' => 'Suprantu. Galite vartoti vitaminą C.', 'en' => 'I understand. You can take vitamin C.', 'trigger_goal' => 'cold', 'priority' => 100],
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
                    ['lt' => 'Štai galimi vaistai.', 'en' => 'Here are possible medicines.'],
                    ['lt' => 'Ar norite paklausti kainos ar kaip vartoti?', 'en' => 'Do you want to ask the price or how to use it?'],
                    ['lt' => 'Prašom. Galite paklausti apie kainą arba vartojimą.', 'en' => 'Please. You can ask about the price or use.'],
                    ['lt' => 'Šis vaistas kainuoja šešis eurus.', 'en' => 'This medicine costs six euros.', 'trigger_goal' => 'ask-price', 'priority' => 100],
                    ['lt' => 'Kaina yra šeši eurai.', 'en' => 'The price is six euros.', 'trigger_goal' => 'ask-price', 'priority' => 100],
                    ['lt' => 'Vartokite po vieną tabletę du kartus per dieną.', 'en' => 'Take one tablet twice a day.', 'trigger_goal' => 'ask-how-to-use', 'priority' => 100],
                    ['lt' => 'Gerkite vieną tabletę ryte ir vakare.', 'en' => 'Take one tablet in the morning and evening.', 'trigger_goal' => 'ask-how-to-use', 'priority' => 100],
                    ['lt' => 'Taip, norint nusipirkti, galite eiti prie kasos.', 'en' => 'Yes, if you want to buy it, you can go to the checkout.', 'trigger_goal' => 'want-buy', 'priority' => 100],
                    ['lt' => 'Gerai, galite pirkti prie kasos.', 'en' => 'Alright, you can buy it at the checkout.', 'trigger_goal' => 'want-buy', 'priority' => 100],
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
                    ['lt' => 'Ar mokėsite kortele ar grynaisiais?', 'en' => 'Will you pay by card or in cash?'],
                    ['lt' => 'Prašom prie kasos.', 'en' => 'Please come to the checkout.'],
                    ['lt' => 'Kaip norėsite mokėti?', 'en' => 'How would you like to pay?'],
                    ['lt' => 'Ačiū. Mokėjimas kortele priimtas.', 'en' => 'Thank you. Card payment accepted.', 'trigger_goal' => 'pay-card', 'priority' => 100],
                    ['lt' => 'Puiku. Kortele apmokėta.', 'en' => 'Great. Paid by card.', 'trigger_goal' => 'pay-card', 'priority' => 100],
                    ['lt' => 'Ačiū. Štai jūsų grąža.', 'en' => 'Thank you. Here is your change.', 'trigger_goal' => 'pay-cash', 'priority' => 100],
                    ['lt' => 'Ačiū. Grynaisiais tinka.', 'en' => 'Thank you. Cash is fine.', 'trigger_goal' => 'pay-cash', 'priority' => 100],
                    ['lt' => 'Prašom. Linkiu greitai pasveikti!', 'en' => 'You are welcome. I hope you get well soon!', 'trigger_goal' => 'say-goodbye', 'priority' => 100],
                    ['lt' => 'Ačiū jums. Iki pasimatymo!', 'en' => 'Thank you. See you!', 'trigger_goal' => 'say-goodbye', 'priority' => 100],
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
