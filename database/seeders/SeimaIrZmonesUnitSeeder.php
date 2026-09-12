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

class SeimaIrZmonesUnitSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $language = Language::query()->where('code', 'lt')->firstOrFail();
            $character = Character::query()->where('slug', 'gabija')->firstOrFail();
            $unit = Unit::query()->updateOrCreate(
                ['slug' => 'seima-ir-zmones'],
                [
                    'language_id' => $language->id,
                    'title' => 'Šeima ir žmonės',
                    'description' => 'A warm speaking unit for talking about family, friends, names, and simple relationships.',
                    'cefr_level' => 'a1',
                    'status' => ContentStatus::Published,
                    'sort_order' => 40,
                    'published_at' => now(),
                ],
            );

            $this->syncTranslations($unit, [
                'title' => ['en' => 'Family and People', 'lt' => 'Šeima ir žmonės'],
                'description' => [
                    'en' => 'A warm speaking unit for talking about family, friends, names, and simple relationships.',
                    'lt' => 'Šiltas kalbėjimo skyrius apie šeimą, draugus, vardus ir paprastus ryšius.',
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

            $model->goals()->whereNotIn('slug', collect($scene['goals'])->pluck('slug')->all())->delete();
        }

        $goals = Goal::query()->whereIn('scene_id', $scenes->pluck('id'))->get()->keyBy('slug');

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
                'slug' => 'mano-seima',
                'title' => 'Mano šeima',
                'title_en' => 'My Family',
                'subtitle' => 'Say who is in your family',
                'subtitle_lt' => 'Pasakykite, kas yra jūsų šeimoje',
                'description' => 'Say one or two simple things about your family.',
                'description_lt' => 'Pasakykite vieną ar du paprastus dalykus apie savo šeimą.',
                'emoji' => '👨‍👩‍👧',
                'tone' => 'primary',
                'sort_order' => 31,
                'scene_slug' => 'seima',
                'goal_slug' => 'say-family',
                'goal_label' => 'Talk about your family',
                'goal_label_lt' => 'Papasakokite apie šeimą',
                'goal_intent' => 'The learner says a simple sentence about family, such as having a mother, father, brother, or sister.',
                'goal_intent_lt' => 'Mokinys pasako paprastą sakinį apie šeimą, pavyzdžiui, kad turi mamą, tėtį, brolį arba sesę.',
                'example' => 'Aš turiu brolį.',
                'openings' => ['Papasakokite apie savo šeimą.', 'Kas yra jūsų šeimoje?'],
                'replies' => ['Labai gerai. Šeima yra svarbi.', 'Puiku, supratau.'],
                'note' => [
                    'en' => [
                        'This lesson helps you say one simple thing about family.',
                        'Useful phrases:',
                        '- Mano mama. = My mother.',
                        '- Mano tėtis. = My father.',
                        '- Aš turiu brolį. = I have a brother.',
                        '- Aš turiu sesę. = I have a sister.',
                        'Tiny grammar: mano means my. Use it before a person word: mano mama, mano brolis.',
                    ],
                    'lt' => [
                        'Ši pamoka padeda pasakyti vieną paprastą dalyką apie šeimą.',
                        'Naudingos frazės:',
                        '- Mano mama.',
                        '- Mano tėtis.',
                        '- Aš turiu brolį.',
                        '- Aš turiu sesę.',
                        'Maža gramatikos pastaba: mano reiškia „my“. Vartokite prieš žmogaus pavadinimą: mano mama, mano brolis.',
                    ],
                ],
            ]),
            $this->scenario([
                'slug' => 'kas-cia',
                'title' => 'Kas čia?',
                'title_en' => 'Who Is This?',
                'subtitle' => 'Identify a person',
                'subtitle_lt' => 'Pasakykite, kas tai yra',
                'description' => 'Say who a person is with a short phrase.',
                'description_lt' => 'Trumpa fraze pasakykite, kas yra žmogus.',
                'emoji' => '👤',
                'tone' => 'mint',
                'sort_order' => 32,
                'scene_slug' => 'zmogus',
                'goal_slug' => 'identify-person',
                'goal_label' => 'Say who this is',
                'goal_label_lt' => 'Pasakykite, kas tai yra',
                'goal_intent' => 'The learner identifies a person using Čia or Tai, such as this is my mother or this is my friend.',
                'goal_intent_lt' => 'Mokinys įvardija žmogų vartodamas čia arba tai, pavyzdžiui, čia mano mama arba tai mano draugas.',
                'example' => 'Čia mano mama.',
                'openings' => ['Pažiūrėkite. Kas čia?', 'Kas yra šis žmogus?'],
                'replies' => ['Ačiū. Dabar supratau.', 'Gerai. Malonu susipažinti.'],
                'note' => [
                    'en' => [
                        'This lesson practises identifying a person.',
                        'Useful phrases:',
                        '- Kas čia? = Who is this?',
                        '- Čia mano mama. = This is my mother.',
                        '- Tai mano draugas. = This is my friend.',
                        '- Tai mano kolegė. = This is my colleague.',
                        'Tiny grammar: čia and tai both work for simple introductions. Keep the sentence short.',
                    ],
                    'lt' => [
                        'Ši pamoka praktikuoja žmogaus įvardijimą.',
                        'Naudingos frazės:',
                        '- Kas čia?',
                        '- Čia mano mama.',
                        '- Tai mano draugas.',
                        '- Tai mano kolegė.',
                        'Maža gramatikos pastaba: čia ir tai tinka paprastam pristatymui. Sakinį laikykite trumpą.',
                    ],
                ],
            ]),
            $this->scenario([
                'slug' => 'jo-jos-vardas',
                'title' => 'Jo / jos vardas',
                'title_en' => 'His / Her Name',
                'subtitle' => 'Say another person’s name',
                'subtitle_lt' => 'Pasakykite kito žmogaus vardą',
                'description' => 'Say a simple sentence with his name or her name.',
                'description_lt' => 'Pasakykite paprastą sakinį su jo arba jos vardu.',
                'emoji' => '🏷️',
                'tone' => 'sky',
                'sort_order' => 33,
                'scene_slug' => 'vardas',
                'goal_slug' => 'say-other-name',
                'goal_label' => 'Say his or her name',
                'goal_label_lt' => 'Pasakykite jo arba jos vardą',
                'goal_intent' => 'The learner says another person’s name using jo vardas or jos vardas.',
                'goal_intent_lt' => 'Mokinys pasako kito žmogaus vardą vartodamas jo vardas arba jos vardas.',
                'example' => 'Jos vardas Rūta.',
                'openings' => ['Koks jos vardas?', 'Koks jo vardas?'],
                'replies' => ['Gražus vardas.', 'Ačiū, dabar žinau vardą.'],
                'note' => [
                    'en' => [
                        'This lesson helps you talk about another person’s name.',
                        'Useful phrases:',
                        '- Jo vardas Tomas. = His name is Tomas.',
                        '- Jos vardas Rūta. = Her name is Rūta.',
                        '- Koks jo vardas? = What is his name?',
                        '- Koks jos vardas? = What is her name?',
                        'Tiny grammar: jo means his. Jos means her.',
                    ],
                    'lt' => [
                        'Ši pamoka padeda kalbėti apie kito žmogaus vardą.',
                        'Naudingos frazės:',
                        '- Jo vardas Tomas.',
                        '- Jos vardas Rūta.',
                        '- Koks jo vardas?',
                        '- Koks jos vardas?',
                        'Maža gramatikos pastaba: jo reiškia „his“. Jos reiškia „her“.',
                    ],
                ],
            ]),
            $this->scenario([
                'slug' => 'ar-turite-vaiku',
                'title' => 'Ar turite vaikų?',
                'title_en' => 'Do You Have Children?',
                'subtitle' => 'Answer a personal yes/no question',
                'subtitle_lt' => 'Atsakykite į paprastą taip/ne klausimą',
                'description' => 'Answer politely about children or family.',
                'description_lt' => 'Mandagiai atsakykite apie vaikus arba šeimą.',
                'emoji' => '🧒',
                'tone' => 'amber',
                'sort_order' => 34,
                'scene_slug' => 'vaikai',
                'goal_slug' => 'answer-children',
                'goal_label' => 'Answer yes or no',
                'goal_label_lt' => 'Atsakykite taip arba ne',
                'goal_intent' => 'The learner answers whether they have children, using a short yes or no sentence.',
                'goal_intent_lt' => 'Mokinys trumpu taip arba ne sakiniu atsako, ar turi vaikų.',
                'example' => 'Ne, vaikų neturiu.',
                'openings' => ['Ar turite vaikų?', 'O vaikų turite?'],
                'replies' => ['Supratau, ačiū.', 'Gerai. Ačiū už atsakymą.'],
                'note' => [
                    'en' => [
                        'This lesson practises a personal yes/no answer.',
                        'Useful phrases:',
                        '- Ar turite vaikų? = Do you have children?',
                        '- Taip, turiu vaiką. = Yes, I have a child.',
                        '- Taip, turiu vaikų. = Yes, I have children.',
                        '- Ne, vaikų neturiu. = No, I do not have children.',
                        'Tiny grammar: neturiu means I do not have.',
                    ],
                    'lt' => [
                        'Ši pamoka praktikuoja asmeninį taip/ne atsakymą.',
                        'Naudingos frazės:',
                        '- Ar turite vaikų?',
                        '- Taip, turiu vaiką.',
                        '- Taip, turiu vaikų.',
                        '- Ne, vaikų neturiu.',
                        'Maža gramatikos pastaba: neturiu reiškia „I do not have“.',
                    ],
                ],
            ]),
            $this->scenario([
                'slug' => 'draugas-ar-kolega',
                'title' => 'Draugas ar kolega?',
                'title_en' => 'Friend or Colleague?',
                'subtitle' => 'Describe a simple relationship',
                'subtitle_lt' => 'Pasakykite paprastą ryšį',
                'description' => 'Say whether someone is your friend, colleague, or teacher.',
                'description_lt' => 'Pasakykite, ar žmogus yra draugas, kolega arba mokytojas.',
                'emoji' => '🤝',
                'tone' => 'berry',
                'sort_order' => 35,
                'scene_slug' => 'rysys',
                'goal_slug' => 'say-relationship',
                'goal_label' => 'Say the relationship',
                'goal_label_lt' => 'Pasakykite ryšį',
                'goal_intent' => 'The learner says a simple relationship, such as this is my friend, colleague, teacher, or neighbour.',
                'goal_intent_lt' => 'Mokinys pasako paprastą ryšį, pavyzdžiui, tai mano draugas, kolega, mokytoja arba kaimynas.',
                'example' => 'Tai mano draugė.',
                'openings' => ['Kas ji jums?', 'Ar jis draugas ar kolega?'],
                'replies' => ['Aišku. Malonu.', 'Gerai, supratau.'],
                'note' => [
                    'en' => [
                        'This lesson gives you simple relationship words.',
                        'Useful phrases:',
                        '- Tai mano draugas. = This is my male friend.',
                        '- Tai mano draugė. = This is my female friend.',
                        '- Tai mano kolega. = This is my male colleague.',
                        '- Tai mano kolegė. = This is my female colleague.',
                        'Tiny grammar: some person words change for male/female: draugas/draugė, kolega/kolegė.',
                    ],
                    'lt' => [
                        'Ši pamoka duoda paprastus ryšių žodžius.',
                        'Naudingos frazės:',
                        '- Tai mano draugas.',
                        '- Tai mano draugė.',
                        '- Tai mano kolega.',
                        '- Tai mano kolegė.',
                        'Maža gramatikos pastaba: kai kurie žmonių žodžiai turi vyrišką ir moterišką formą: draugas/draugė, kolega/kolegė.',
                    ],
                ],
            ]),
            $this->scenario([
                'slug' => 'pristatykite-zmogu',
                'title' => 'Pristatykite žmogų',
                'title_en' => 'Introduce Someone',
                'subtitle' => 'Put people phrases together',
                'subtitle_lt' => 'Sujunkite frazes apie žmones',
                'description' => 'Introduce one person with their relationship and name.',
                'description_lt' => 'Pristatykite vieną žmogų, pasakydami ryšį ir vardą.',
                'emoji' => '💬',
                'tone' => 'primary',
                'sort_order' => 36,
                'scene_slug' => 'pristatymas',
                'goal_slug' => 'introduce-person',
                'goal_label' => 'Introduce one person',
                'goal_label_lt' => 'Pristatykite vieną žmogų',
                'goal_intent' => 'The learner introduces one person using a simple relationship and name.',
                'goal_intent_lt' => 'Mokinys pristato vieną žmogų vartodamas paprastą ryšį ir vardą.',
                'example' => 'Čia mano draugė. Jos vardas Rūta.',
                'openings' => ['Prašau pristatykite šį žmogų.', 'Kas čia? Pasakykite trumpai.'],
                'replies' => ['Labai gerai. Malonu susipažinti.', 'Puiku. Aiškus pristatymas.'],
                'note' => [
                    'en' => [
                        'This is the short review for the family and people unit.',
                        'Useful flow:',
                        '- Čia mano draugė. = This is my female friend.',
                        '- Jos vardas Rūta. = Her name is Rūta.',
                        '- Tai mano brolis. = This is my brother.',
                        '- Jo vardas Tomas. = His name is Tomas.',
                        'Goal: two short sentences are enough.',
                    ],
                    'lt' => [
                        'Tai trumpas šeimos ir žmonių skyriaus pakartojimas.',
                        'Naudinga seka:',
                        '- Čia mano draugė.',
                        '- Jos vardas Rūta.',
                        '- Tai mano brolis.',
                        '- Jo vardas Tomas.',
                        'Tikslas: pakanka dviejų trumpų sakinių.',
                    ],
                ],
            ]),
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
            'Papasakokite apie savo šeimą.' => 'Tell me about your family.',
            'Kas yra jūsų šeimoje?' => 'Who is in your family?',
            'Labai gerai. Šeima yra svarbi.' => 'Very good. Family is important.',
            'Puiku, supratau.' => 'Great, I understand.',
            'Pažiūrėkite. Kas čia?' => 'Look. Who is this?',
            'Kas yra šis žmogus?' => 'Who is this person?',
            'Ačiū. Dabar supratau.' => 'Thank you. Now I understand.',
            'Gerai. Malonu susipažinti.' => 'Good. Nice to meet them.',
            'Koks jos vardas?' => 'What is her name?',
            'Koks jo vardas?' => 'What is his name?',
            'Gražus vardas.' => 'A beautiful name.',
            'Ačiū, dabar žinau vardą.' => 'Thank you, now I know the name.',
            'Ar turite vaikų?' => 'Do you have children?',
            'O vaikų turite?' => 'And do you have children?',
            'Supratau, ačiū.' => 'I understand, thank you.',
            'Gerai. Ačiū už atsakymą.' => 'Good. Thank you for the answer.',
            'Kas ji jums?' => 'Who is she to you?',
            'Ar jis draugas ar kolega?' => 'Is he a friend or a colleague?',
            'Aišku. Malonu.' => 'Clear. Nice.',
            'Gerai, supratau.' => 'Good, I understand.',
            'Prašau pristatykite šį žmogų.' => 'Please introduce this person.',
            'Kas čia? Pasakykite trumpai.' => 'Who is this? Say it briefly.',
            'Labai gerai. Malonu susipažinti.' => 'Very good. Nice to meet them.',
            'Puiku. Aiškus pristatymas.' => 'Great. A clear introduction.',
            default => $line,
        };
    }
}
