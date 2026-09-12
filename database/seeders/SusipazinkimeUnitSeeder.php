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

class SusipazinkimeUnitSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $language = Language::query()->where('code', 'lt')->firstOrFail();
            $character = Character::query()->where('slug', 'gabija')->firstOrFail();
            $unit = Unit::query()->updateOrCreate(
                ['slug' => 'susipazinkime'],
                [
                    'language_id' => $language->id,
                    'title' => 'Susipažinkime',
                    'description' => 'A first speaking unit for greetings, names, simple identity questions, origin, roles, and basic negation.',
                    'cefr_level' => 'a1',
                    'status' => ContentStatus::Published,
                    'sort_order' => 10,
                    'published_at' => now(),
                ],
            );

            $this->syncTranslations($unit, [
                'title' => [
                    'en' => 'Let’s Get Acquainted',
                    'lt' => 'Susipažinkime',
                ],
                'description' => [
                    'en' => 'A first speaking unit for greetings, names, simple identity questions, origin, roles, and basic negation.',
                    'lt' => 'Pirmasis kalbėjimo skyrius apie pasisveikinimą, vardą, paprastus klausimus apie tapatybę, kilmę, vaidmenis ir neiginį.',
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
        $model = $scenario->note()->updateOrCreate(
            [],
            [
                'title' => $note['title'],
                'body' => $note['body'],
                'cefr_level' => 'a1',
                'estimated_minutes' => $note['estimated_minutes'] ?? 2,
                'status' => ContentStatus::Published,
            ],
        );

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
            foreach ($scene['goals'] as $index => $goal) {
                $model = $scenes->get($scene['slug'])->goals()->updateOrCreate(
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

                $this->syncTranslations($model, $goal['translations']);
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
                $npcLine = $model->npcLines()->create([
                    'trigger_goal_id' => $line['trigger_goal'] ? $goals->get($line['trigger_goal'])?->id : null,
                    'target_text' => $line['target_text'],
                    'support_translation' => $line['support_translation'],
                    'cefr_level' => 'a1',
                    'priority' => $line['priority'] ?? 0,
                    'sort_order' => ($index + 1) * 10,
                ]);

                $this->syncTranslations($npcLine, [
                    'support_translation' => [
                        'en' => $line['support_translation'],
                        'lt' => $line['target_text'],
                    ],
                ]);
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $sceneData
     */
    private function deleteRemovedScenes(Scenario $scenario, array $sceneData): void
    {
        $slugs = collect($sceneData)->pluck('slug')->all();

        $scenario->scenes()
            ->whereNotIn('slug', $slugs)
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
            [
                'slug' => 'pasisveikinimas',
                'title' => 'Pasisveikinimas',
                'subtitle' => 'Greetings and goodbyes',
                'description' => 'Greet Gabija and answer a simple greeting.',
                'emoji' => '👋',
                'tone' => 'primary',
                'is_free' => true,
                'sort_order' => 1,
                'start_scene' => 'rytas',
                'translations' => [
                    'title' => ['en' => 'Greetings', 'lt' => 'Pasisveikinimas'],
                    'subtitle' => ['en' => 'Greetings and goodbyes', 'lt' => 'Pasisveikinimai ir atsisveikinimai'],
                    'description' => ['en' => 'Greet Gabija and answer a simple greeting.', 'lt' => 'Pasisveikinkite su Gabija ir atsakykite į paprastą pasisveikinimą.'],
                ],
                'note' => [
                    'title' => 'Before you greet someone',
                    'body' => implode("\n\n", [
                        'This short practice helps you speak from the first minute.',
                        'Useful phrases:',
                        '- Labas. = Hi.',
                        '- Laba diena. = Good day.',
                        '- Labas rytas. = Good morning.',
                        '- Labas vakaras. = Good evening.',
                        '- Viso gero. = Goodbye.',
                        'Tip: use labas with friends or children. Use laba diena when you want to sound polite.',
                    ]),
                    'translations' => [
                        'title' => ['en' => 'Before you greet someone', 'lt' => 'Prieš pasisveikinant'],
                        'body' => ['en' => implode("\n\n", [
                            'This short practice helps you speak from the first minute.',
                            'Useful phrases:',
                            '- Labas. = Hi.',
                            '- Laba diena. = Good day.',
                            '- Labas rytas. = Good morning.',
                            '- Labas vakaras. = Good evening.',
                            '- Viso gero. = Goodbye.',
                            'Tip: use labas with friends or children. Use laba diena when you want to sound polite.',
                        ]), 'lt' => implode("\n\n", [
                            'Ši trumpa praktika padeda pradėti kalbėti nuo pirmos minutės.',
                            'Naudingos frazės:',
                            '- Labas.',
                            '- Laba diena.',
                            '- Labas rytas.',
                            '- Labas vakaras.',
                            '- Viso gero.',
                            'Patarimas: labas tinka draugams ar vaikams. Laba diena skamba mandagiau.',
                        ])],
                    ],
                ],
                'scenes' => [
                    [
                        'slug' => 'rytas',
                        'title' => 'Morning greeting',
                        'setting' => 'Gabija meets the learner in the morning.',
                        'translations' => [
                            'title' => ['en' => 'Morning greeting', 'lt' => 'Rytinis pasisveikinimas'],
                            'setting' => ['en' => 'Gabija meets the learner in the morning.', 'lt' => 'Gabija ryte susitinka su mokiniu.'],
                        ],
                        'lines' => [
                            ['target_text' => 'Labas rytas!', 'support_translation' => 'Good morning!', 'trigger_goal' => null],
                            ['target_text' => 'Sveiki! Labas rytas.', 'support_translation' => 'Hello! Good morning.', 'trigger_goal' => null],
                            ['target_text' => 'Puiku. Labas rytas!', 'support_translation' => 'Great. Good morning!', 'trigger_goal' => 'say-good-morning', 'priority' => 100],
                            ['target_text' => 'Labai gerai. Jūs pasisveikinote.', 'support_translation' => 'Very good. You greeted someone.', 'trigger_goal' => 'say-good-morning', 'priority' => 100],
                        ],
                        'goals' => [
                            [
                                'slug' => 'say-good-morning',
                                'label' => 'Say good morning',
                                'intent' => 'The learner greets Gabija in the morning.',
                                'example' => 'Labas rytas.',
                                'next' => 'atsisveikinimas',
                                'translations' => [
                                    'label' => ['en' => 'Say good morning', 'lt' => 'Pasakykite „labas rytas“'],
                                    'intent' => ['en' => 'The learner greets Gabija in the morning.', 'lt' => 'Mokinys ryte pasisveikina su Gabija.'],
                                ],
                            ],
                        ],
                    ],
                    [
                        'slug' => 'atsisveikinimas',
                        'title' => 'Goodbye',
                        'setting' => 'Gabija ends the short meeting politely.',
                        'translations' => [
                            'title' => ['en' => 'Goodbye', 'lt' => 'Atsisveikinimas'],
                            'setting' => ['en' => 'Gabija ends the short meeting politely.', 'lt' => 'Gabija mandagiai baigia trumpą susitikimą.'],
                        ],
                        'lines' => [
                            ['target_text' => 'Dabar atsisveikinkime. Ką sakysite?', 'support_translation' => 'Now let’s say goodbye. What will you say?', 'trigger_goal' => null],
                            ['target_text' => 'Pabaigai pasakykite „viso gero“.', 'support_translation' => 'To finish, say “goodbye.”', 'trigger_goal' => null],
                            ['target_text' => 'Viso gero! Iki kito karto.', 'support_translation' => 'Goodbye! See you next time.', 'trigger_goal' => 'say-goodbye', 'priority' => 100],
                            ['target_text' => 'Puiku. Viso gero!', 'support_translation' => 'Great. Goodbye!', 'trigger_goal' => 'say-goodbye', 'priority' => 100],
                        ],
                        'goals' => [
                            [
                                'slug' => 'say-goodbye',
                                'label' => 'Say goodbye',
                                'intent' => 'The learner says goodbye.',
                                'example' => 'Viso gero.',
                                'next' => null,
                                'translations' => [
                                    'label' => ['en' => 'Say goodbye', 'lt' => 'Atsisveikinkite'],
                                    'intent' => ['en' => 'The learner says goodbye.', 'lt' => 'Mokinys atsisveikina.'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'mano-vardas',
                'title' => 'Mano vardas',
                'subtitle' => 'Say and ask names',
                'description' => 'Say your name and ask another person’s name.',
                'emoji' => '🙂',
                'tone' => 'mint',
                'is_free' => true,
                'sort_order' => 2,
                'start_scene' => 'vardas',
                'translations' => [
                    'title' => ['en' => 'My Name', 'lt' => 'Mano vardas'],
                    'subtitle' => ['en' => 'Say and ask names', 'lt' => 'Pasakykite ir paklauskite vardo'],
                    'description' => ['en' => 'Say your name and ask another person’s name.', 'lt' => 'Pasakykite savo vardą ir paklauskite kito žmogaus vardo.'],
                ],
                'note' => [
                    'title' => 'Before you say your name',
                    'body' => implode("\n\n", [
                        'Use a very short sentence first. You do not need complex grammar yet.',
                        'Useful phrases:',
                        '- Aš esu ... = I am ...',
                        '- Mano vardas ... = My name is ...',
                        '- Koks jūsų vardas? = What is your name? (polite)',
                        '- Kuo tu vardu? = What is your name? (informal)',
                        'Grammar tip: aš means I. Jūs is polite/formal you. Tu is informal you.',
                    ]),
                    'translations' => [
                        'title' => ['en' => 'Before you say your name', 'lt' => 'Prieš sakant savo vardą'],
                        'body' => ['en' => implode("\n\n", [
                            'Use a very short sentence first. You do not need complex grammar yet.',
                            'Useful phrases:',
                            '- Aš esu ... = I am ...',
                            '- Mano vardas ... = My name is ...',
                            '- Koks jūsų vardas? = What is your name? (polite)',
                            '- Kuo tu vardu? = What is your name? (informal)',
                            'Grammar tip: aš means I. Jūs is polite/formal you. Tu is informal you.',
                        ]), 'lt' => implode("\n\n", [
                            'Pirmiausia vartokite labai trumpą sakinį. Sudėtingos gramatikos dar nereikia.',
                            'Naudingos frazės:',
                            '- Aš esu ...',
                            '- Mano vardas ...',
                            '- Koks jūsų vardas?',
                            '- Kuo tu vardu?',
                            'Gramatikos patarimas: aš reiškia „I“. Jūs yra mandagus kreipinys. Tu yra neformalus kreipinys.',
                        ])],
                    ],
                ],
                'scenes' => [
                    [
                        'slug' => 'vardas',
                        'title' => 'Your name',
                        'setting' => 'Gabija asks for the learner’s name.',
                        'translations' => [
                            'title' => ['en' => 'Your name', 'lt' => 'Jūsų vardas'],
                            'setting' => ['en' => 'Gabija asks for the learner’s name.', 'lt' => 'Gabija klausia mokinio vardo.'],
                        ],
                        'lines' => [
                            ['target_text' => 'Labas! Kuo tu vardu?', 'support_translation' => 'Hi! What is your name?', 'trigger_goal' => null],
                            ['target_text' => 'Sveiki. Koks jūsų vardas?', 'support_translation' => 'Hello. What is your name?', 'trigger_goal' => null],
                            ['target_text' => 'Malonu susipažinti.', 'support_translation' => 'Nice to meet you.', 'trigger_goal' => 'say-name', 'priority' => 100],
                            ['target_text' => 'Ačiū. Labai malonu.', 'support_translation' => 'Thank you. Very nice to meet you.', 'trigger_goal' => 'say-name', 'priority' => 100],
                        ],
                        'goals' => [
                            [
                                'slug' => 'say-name',
                                'label' => 'Say your name',
                                'intent' => 'The learner says their name.',
                                'example' => 'Mano vardas Deividas.',
                                'next' => 'klauskite-vardo',
                                'translations' => [
                                    'label' => ['en' => 'Say your name', 'lt' => 'Pasakykite savo vardą'],
                                    'intent' => ['en' => 'The learner says their name.', 'lt' => 'Mokinys pasako savo vardą.'],
                                ],
                            ],
                        ],
                    ],
                    [
                        'slug' => 'klauskite-vardo',
                        'title' => 'Ask a name',
                        'setting' => 'The learner asks Gabija for her name.',
                        'translations' => [
                            'title' => ['en' => 'Ask a name', 'lt' => 'Paklauskite vardo'],
                            'setting' => ['en' => 'The learner asks Gabija for her name.', 'lt' => 'Mokinys klausia Gabijos vardo.'],
                        ],
                        'lines' => [
                            ['target_text' => 'Dabar paklauskite mano vardo.', 'support_translation' => 'Now ask my name.', 'trigger_goal' => null],
                            ['target_text' => 'O kaip paklausite mano vardo?', 'support_translation' => 'And how will you ask my name?', 'trigger_goal' => null],
                            ['target_text' => 'Aš Gabija.', 'support_translation' => 'I am Gabija.', 'trigger_goal' => 'ask-name', 'priority' => 100],
                            ['target_text' => 'Mano vardas Gabija.', 'support_translation' => 'My name is Gabija.', 'trigger_goal' => 'ask-name', 'priority' => 100],
                        ],
                        'goals' => [
                            [
                                'slug' => 'ask-name',
                                'label' => 'Ask her name',
                                'intent' => 'The learner asks another person’s name.',
                                'example' => 'Koks jūsų vardas?',
                                'next' => null,
                                'translations' => [
                                    'label' => ['en' => 'Ask her name', 'lt' => 'Paklauskite jos vardo'],
                                    'intent' => ['en' => 'The learner asks another person’s name.', 'lt' => 'Mokinys paklausia kito žmogaus vardo.'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'ar-jus-esate',
                'title' => 'Ar jūs esate...?',
                'subtitle' => 'Yes, no, and not',
                'description' => 'Confirm or deny a simple identity question.',
                'emoji' => '❓',
                'tone' => 'sky',
                'sort_order' => 3,
                'start_scene' => 'patvirtinimas',
                'translations' => [
                    'title' => ['en' => 'Are You...?', 'lt' => 'Ar jūs esate...?'],
                    'subtitle' => ['en' => 'Yes, no, and not', 'lt' => 'Taip, ne ir neiginys'],
                    'description' => ['en' => 'Confirm or deny a simple identity question.', 'lt' => 'Patvirtinkite arba paneikite paprastą klausimą apie tapatybę.'],
                ],
                'note' => [
                    'title' => 'Before yes and no questions',
                    'body' => implode("\n\n", [
                        'Lithuanian yes/no questions often begin with ar.',
                        'Useful phrases:',
                        '- Ar jūs esate Tomas? = Are you Tomas?',
                        '- Taip, aš esu ... = Yes, I am ...',
                        '- Ne, aš nesu ... = No, I am not ...',
                        '- Aš esu studentas. = I am a student.',
                        'Grammar tip: esu means am. Nesu means am not.',
                    ]),
                    'translations' => [
                        'title' => ['en' => 'Before yes and no questions', 'lt' => 'Prieš klausimus su „ar“'],
                        'body' => ['en' => implode("\n\n", [
                            'Lithuanian yes/no questions often begin with ar.',
                            'Useful phrases:',
                            '- Ar jūs esate Tomas? = Are you Tomas?',
                            '- Taip, aš esu ... = Yes, I am ...',
                            '- Ne, aš nesu ... = No, I am not ...',
                            '- Aš esu studentas. = I am a student.',
                            'Grammar tip: esu means am. Nesu means am not.',
                        ]), 'lt' => implode("\n\n", [
                            'Lietuvių kalboje taip/ne klausimai dažnai prasideda žodžiu ar.',
                            'Naudingos frazės:',
                            '- Ar jūs esate Tomas?',
                            '- Taip, aš esu ...',
                            '- Ne, aš nesu ...',
                            '- Aš esu studentas.',
                            'Gramatikos patarimas: esu reiškia „am“. Nesu reiškia „am not“.',
                        ])],
                    ],
                ],
                'scenes' => [
                    [
                        'slug' => 'patvirtinimas',
                        'title' => 'Say yes',
                        'setting' => 'Gabija asks whether the learner is a student.',
                        'translations' => [
                            'title' => ['en' => 'Say yes', 'lt' => 'Pasakykite „taip“'],
                            'setting' => ['en' => 'Gabija asks whether the learner is a student.', 'lt' => 'Gabija klausia, ar mokinys yra studentas.'],
                        ],
                        'lines' => [
                            ['target_text' => 'Ar jūs esate studentas?', 'support_translation' => 'Are you a student?', 'trigger_goal' => null],
                            ['target_text' => 'Paklausiu paprastai: ar jūs esate studentas?', 'support_translation' => 'I will ask simply: are you a student?', 'trigger_goal' => null],
                            ['target_text' => 'Gerai. Jūs atsakėte teigiamai.', 'support_translation' => 'Good. You answered yes.', 'trigger_goal' => 'answer-yes', 'priority' => 100],
                            ['target_text' => 'Puiku. „Taip, aš esu“ skamba aiškiai.', 'support_translation' => 'Great. “Yes, I am” sounds clear.', 'trigger_goal' => 'answer-yes', 'priority' => 100],
                        ],
                        'goals' => [
                            [
                                'slug' => 'answer-yes',
                                'label' => 'Answer yes',
                                'intent' => 'The learner confirms a simple identity statement.',
                                'example' => 'Taip, aš esu studentas.',
                                'next' => 'neiginys',
                                'translations' => [
                                    'label' => ['en' => 'Answer yes', 'lt' => 'Atsakykite „taip“'],
                                    'intent' => ['en' => 'The learner confirms a simple identity statement.', 'lt' => 'Mokinys patvirtina paprastą teiginį apie tapatybę.'],
                                ],
                            ],
                        ],
                    ],
                    [
                        'slug' => 'neiginys',
                        'title' => 'Say no',
                        'setting' => 'Gabija asks a simple identity question that the learner denies.',
                        'translations' => [
                            'title' => ['en' => 'Say no', 'lt' => 'Pasakykite „ne“'],
                            'setting' => ['en' => 'Gabija asks a simple identity question that the learner denies.', 'lt' => 'Gabija klausia paprasto klausimo, į kurį mokinys atsako neigiamai.'],
                        ],
                        'lines' => [
                            ['target_text' => 'Ar jūs esate mokytojas?', 'support_translation' => 'Are you a teacher?', 'trigger_goal' => null],
                            ['target_text' => 'Dabar atsakykite neigiamai: ar jūs esate mokytojas?', 'support_translation' => 'Now answer negatively: are you a teacher?', 'trigger_goal' => null],
                            ['target_text' => 'Teisingai. „Nesu“ reiškia „am not“.', 'support_translation' => 'Correct. “Nesu” means “am not.”', 'trigger_goal' => 'answer-no', 'priority' => 100],
                            ['target_text' => 'Labai gerai. Jūs pavartojote neiginį.', 'support_translation' => 'Very good. You used negation.', 'trigger_goal' => 'answer-no', 'priority' => 100],
                        ],
                        'goals' => [
                            [
                                'slug' => 'answer-no',
                                'label' => 'Answer no',
                                'intent' => 'The learner denies a simple identity statement with ne or nesu.',
                                'example' => 'Ne, aš nesu mokytojas.',
                                'next' => null,
                                'translations' => [
                                    'label' => ['en' => 'Answer no', 'lt' => 'Atsakykite „ne“'],
                                    'intent' => ['en' => 'The learner denies a simple identity statement with ne or nesu.', 'lt' => 'Mokinys paneigia paprastą teiginį vartodamas ne arba nesu.'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'is-kur-esate',
                'title' => 'Iš kur jūs esate?',
                'subtitle' => 'Say where you are from',
                'description' => 'Answer a simple question about your country or city.',
                'emoji' => '🌍',
                'tone' => 'primary',
                'sort_order' => 4,
                'start_scene' => 'salis',
                'translations' => [
                    'title' => ['en' => 'Where Are You From?', 'lt' => 'Iš kur jūs esate?'],
                    'subtitle' => ['en' => 'Say where you are from', 'lt' => 'Pasakykite, iš kur esate'],
                    'description' => ['en' => 'Answer a simple question about your country or city.', 'lt' => 'Atsakykite į paprastą klausimą apie savo šalį ar miestą.'],
                ],
                'note' => [
                    'title' => 'Before saying where you are from',
                    'body' => implode("\n\n", [
                        'Iš kur means where from.',
                        'Useful phrases:',
                        '- Iš kur jūs esate? = Where are you from?',
                        '- Aš esu iš ... = I am from ...',
                        '- Aš iš ... = I am from ...',
                        '- Aš gyvenu Vilniuje. = I live in Vilnius.',
                        'Keep the answer simple. The judge should accept real countries and cities.',
                    ]),
                    'translations' => [
                        'title' => ['en' => 'Before saying where you are from', 'lt' => 'Prieš sakant, iš kur esate'],
                        'body' => ['en' => implode("\n\n", [
                            'Iš kur means where from.',
                            'Useful phrases:',
                            '- Iš kur jūs esate? = Where are you from?',
                            '- Aš esu iš ... = I am from ...',
                            '- Aš iš ... = I am from ...',
                            '- Aš gyvenu Vilniuje. = I live in Vilnius.',
                            'Keep the answer simple. The judge should accept real countries and cities.',
                        ]), 'lt' => implode("\n\n", [
                            'Iš kur klausia kilmės ar vietos.',
                            'Naudingos frazės:',
                            '- Iš kur jūs esate?',
                            '- Aš esu iš ...',
                            '- Aš iš ...',
                            '- Aš gyvenu Vilniuje.',
                            'Atsakykite paprastai. Vertintojas turi priimti tikras šalis ir miestus.',
                        ])],
                    ],
                ],
                'scenes' => [
                    [
                        'slug' => 'salis',
                        'title' => 'Country',
                        'setting' => 'Gabija asks where the learner is from.',
                        'translations' => [
                            'title' => ['en' => 'Country', 'lt' => 'Šalis'],
                            'setting' => ['en' => 'Gabija asks where the learner is from.', 'lt' => 'Gabija klausia, iš kur mokinys yra.'],
                        ],
                        'lines' => [
                            ['target_text' => 'Iš kur jūs esate?', 'support_translation' => 'Where are you from?', 'trigger_goal' => null],
                            ['target_text' => 'Pasakykite, iš kur jūs esate.', 'support_translation' => 'Say where you are from.', 'trigger_goal' => null],
                            ['target_text' => 'Ačiū. Dabar žinau, iš kur jūs esate.', 'support_translation' => 'Thank you. Now I know where you are from.', 'trigger_goal' => 'say-origin', 'priority' => 100],
                            ['target_text' => 'Puiku. Atsakymas buvo aiškus.', 'support_translation' => 'Great. The answer was clear.', 'trigger_goal' => 'say-origin', 'priority' => 100],
                        ],
                        'goals' => [
                            [
                                'slug' => 'say-origin',
                                'label' => 'Say where you are from',
                                'intent' => 'The learner says what country or city they are from.',
                                'example' => 'Aš esu iš Nigerijos.',
                                'next' => 'gyvenu',
                                'translations' => [
                                    'label' => ['en' => 'Say where you are from', 'lt' => 'Pasakykite, iš kur esate'],
                                    'intent' => ['en' => 'The learner says what country or city they are from.', 'lt' => 'Mokinys pasako, iš kokios šalies ar miesto yra.'],
                                ],
                            ],
                        ],
                    ],
                    [
                        'slug' => 'gyvenu',
                        'title' => 'Where you live',
                        'setting' => 'Gabija asks where the learner lives now.',
                        'translations' => [
                            'title' => ['en' => 'Where you live', 'lt' => 'Kur gyvenate'],
                            'setting' => ['en' => 'Gabija asks where the learner lives now.', 'lt' => 'Gabija klausia, kur mokinys dabar gyvena.'],
                        ],
                        'lines' => [
                            ['target_text' => 'Kur dabar gyvenate?', 'support_translation' => 'Where do you live now?', 'trigger_goal' => null],
                            ['target_text' => 'O kur jūs gyvenate dabar?', 'support_translation' => 'And where do you live now?', 'trigger_goal' => null],
                            ['target_text' => 'Labai gerai. Ačiū.', 'support_translation' => 'Very good. Thank you.', 'trigger_goal' => 'say-live-place', 'priority' => 100],
                            ['target_text' => 'Supratau. Ačiū už atsakymą.', 'support_translation' => 'I understand. Thank you for the answer.', 'trigger_goal' => 'say-live-place', 'priority' => 100],
                        ],
                        'goals' => [
                            [
                                'slug' => 'say-live-place',
                                'label' => 'Say where you live',
                                'intent' => 'The learner says where they live now.',
                                'example' => 'Aš gyvenu Vilniuje.',
                                'next' => null,
                                'translations' => [
                                    'label' => ['en' => 'Say where you live', 'lt' => 'Pasakykite, kur gyvenate'],
                                    'intent' => ['en' => 'The learner says where they live now.', 'lt' => 'Mokinys pasako, kur dabar gyvena.'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'kas-jus-esate',
                'title' => 'Kas jūs esate?',
                'subtitle' => 'Say a simple role',
                'description' => 'Say whether you are a student, worker, teacher, or something else simple.',
                'emoji' => '🪪',
                'tone' => 'berry',
                'sort_order' => 5,
                'start_scene' => 'vaidmuo',
                'translations' => [
                    'title' => ['en' => 'Who Are You?', 'lt' => 'Kas jūs esate?'],
                    'subtitle' => ['en' => 'Say a simple role', 'lt' => 'Pasakykite paprastą vaidmenį'],
                    'description' => ['en' => 'Say whether you are a student, worker, teacher, or something else simple.', 'lt' => 'Pasakykite, ar esate studentas, darbuotojas, mokytojas ar kitas paprastas vaidmuo.'],
                ],
                'note' => [
                    'title' => 'Before saying who you are',
                    'body' => implode("\n\n", [
                        'Kas asks who or what someone is.',
                        'Useful phrases:',
                        '- Kas jūs esate? = Who are you?',
                        '- Aš esu studentas. = I am a male student.',
                        '- Aš esu studentė. = I am a female student.',
                        '- Aš dirbu. = I work.',
                        '- Aš mokausi. = I study.',
                        'Gender note: some Lithuanian role words change ending, for example studentas/studentė.',
                    ]),
                    'translations' => [
                        'title' => ['en' => 'Before saying who you are', 'lt' => 'Prieš sakant, kas esate'],
                        'body' => ['en' => implode("\n\n", [
                            'Kas asks who or what someone is.',
                            'Useful phrases:',
                            '- Kas jūs esate? = Who are you?',
                            '- Aš esu studentas. = I am a male student.',
                            '- Aš esu studentė. = I am a female student.',
                            '- Aš dirbu. = I work.',
                            '- Aš mokausi. = I study.',
                            'Gender note: some Lithuanian role words change ending, for example studentas/studentė.',
                        ]), 'lt' => implode("\n\n", [
                            'Kas klausia žmogaus tapatybės ar vaidmens.',
                            'Naudingos frazės:',
                            '- Kas jūs esate?',
                            '- Aš esu studentas.',
                            '- Aš esu studentė.',
                            '- Aš dirbu.',
                            '- Aš mokausi.',
                            'Pastaba: kai kurie lietuviški vaidmenų žodžiai keičia galūnę, pavyzdžiui, studentas/studentė.',
                        ])],
                    ],
                ],
                'scenes' => [
                    [
                        'slug' => 'vaidmuo',
                        'title' => 'Simple role',
                        'setting' => 'Gabija asks who the learner is in simple terms.',
                        'translations' => [
                            'title' => ['en' => 'Simple role', 'lt' => 'Paprastas vaidmuo'],
                            'setting' => ['en' => 'Gabija asks who the learner is in simple terms.', 'lt' => 'Gabija paprastai klausia, kas yra mokinys.'],
                        ],
                        'lines' => [
                            ['target_text' => 'Kas jūs esate?', 'support_translation' => 'Who are you?', 'trigger_goal' => null],
                            ['target_text' => 'Pasakykite paprastai: kas jūs esate?', 'support_translation' => 'Say it simply: who are you?', 'trigger_goal' => null],
                            ['target_text' => 'Gerai. Tai aiškus atsakymas.', 'support_translation' => 'Good. That is a clear answer.', 'trigger_goal' => 'say-role', 'priority' => 100],
                            ['target_text' => 'Puiku. Trumpas sakinys labai tinka.', 'support_translation' => 'Great. A short sentence works very well.', 'trigger_goal' => 'say-role', 'priority' => 100],
                        ],
                        'goals' => [
                            [
                                'slug' => 'say-role',
                                'label' => 'Say who you are',
                                'intent' => 'The learner says a simple role or identity.',
                                'example' => 'Aš esu studentas.',
                                'next' => 'veiksmas',
                                'translations' => [
                                    'label' => ['en' => 'Say who you are', 'lt' => 'Pasakykite, kas esate'],
                                    'intent' => ['en' => 'The learner says a simple role or identity.', 'lt' => 'Mokinys pasako paprastą vaidmenį ar tapatybę.'],
                                ],
                            ],
                        ],
                    ],
                    [
                        'slug' => 'veiksmas',
                        'title' => 'Work or study',
                        'setting' => 'Gabija asks whether the learner works or studies.',
                        'translations' => [
                            'title' => ['en' => 'Work or study', 'lt' => 'Darbas ar mokslai'],
                            'setting' => ['en' => 'Gabija asks whether the learner works or studies.', 'lt' => 'Gabija klausia, ar mokinys dirba, ar mokosi.'],
                        ],
                        'lines' => [
                            ['target_text' => 'Ar jūs dirbate, ar mokotės?', 'support_translation' => 'Do you work or study?', 'trigger_goal' => null],
                            ['target_text' => 'Ką galite pasakyti: dirbu ar mokausi?', 'support_translation' => 'What can you say: I work or I study?', 'trigger_goal' => null],
                            ['target_text' => 'Labai gerai. Tai naudinga frazė.', 'support_translation' => 'Very good. That is a useful phrase.', 'trigger_goal' => 'say-work-study', 'priority' => 100],
                            ['target_text' => 'Puiku. Atsakėte natūraliai.', 'support_translation' => 'Great. You answered naturally.', 'trigger_goal' => 'say-work-study', 'priority' => 100],
                        ],
                        'goals' => [
                            [
                                'slug' => 'say-work-study',
                                'label' => 'Say work or study',
                                'intent' => 'The learner says they work, study, or both.',
                                'example' => 'Aš dirbu.',
                                'next' => null,
                                'translations' => [
                                    'label' => ['en' => 'Say work or study', 'lt' => 'Pasakykite, ar dirbate, ar mokotės'],
                                    'intent' => ['en' => 'The learner says they work, study, or both.', 'lt' => 'Mokinys pasako, kad dirba, mokosi arba ir dirba, ir mokosi.'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'prisistatymas',
                'title' => 'Trumpas susipažinimas',
                'subtitle' => 'Put the basics together',
                'description' => 'Greet, say your name, say where you are from, and answer one simple identity question.',
                'emoji' => '💬',
                'tone' => 'primary',
                'sort_order' => 6,
                'start_scene' => 'pasisveikinimas',
                'translations' => [
                    'title' => ['en' => 'Short Introduction', 'lt' => 'Trumpas susipažinimas'],
                    'subtitle' => ['en' => 'Put the basics together', 'lt' => 'Sujunkite pagrindus'],
                    'description' => ['en' => 'Greet, say your name, say where you are from, and answer one simple identity question.', 'lt' => 'Pasisveikinkite, pasakykite savo vardą, iš kur esate, ir atsakykite į vieną paprastą klausimą apie tapatybę.'],
                ],
                'note' => [
                    'title' => 'Before your short introduction',
                    'body' => implode("\n\n", [
                        'This is a short recap scenario. Use the phrases you have already practised.',
                        'Useful phrases:',
                        '- Laba diena.',
                        '- Mano vardas ...',
                        '- Aš esu iš ...',
                        '- Aš esu studentas/studentė.',
                        '- Ne, aš nesu ...',
                        'Goal: sound clear and understandable. You do not need a perfect long sentence.',
                    ]),
                    'translations' => [
                        'title' => ['en' => 'Before your short introduction', 'lt' => 'Prieš trumpą prisistatymą'],
                        'body' => ['en' => implode("\n\n", [
                            'This is a short recap scenario. Use the phrases you have already practised.',
                            'Useful phrases:',
                            '- Laba diena.',
                            '- Mano vardas ...',
                            '- Aš esu iš ...',
                            '- Aš esu studentas/studentė.',
                            '- Ne, aš nesu ...',
                            'Goal: sound clear and understandable. You do not need a perfect long sentence.',
                        ]), 'lt' => implode("\n\n", [
                            'Tai trumpas pakartojimo scenarijus. Vartokite frazes, kurias jau praktikavote.',
                            'Naudingos frazės:',
                            '- Laba diena.',
                            '- Mano vardas ...',
                            '- Aš esu iš ...',
                            '- Aš esu studentas/studentė.',
                            '- Ne, aš nesu ...',
                            'Tikslas: kalbėti aiškiai ir suprantamai. Tobulo ilgo sakinio nereikia.',
                        ])],
                    ],
                ],
                'scenes' => [
                    [
                        'slug' => 'pasisveikinimas',
                        'title' => 'Start the introduction',
                        'setting' => 'Gabija meets the learner and asks them to introduce themselves.',
                        'translations' => [
                            'title' => ['en' => 'Start the introduction', 'lt' => 'Pradėkite prisistatymą'],
                            'setting' => ['en' => 'Gabija meets the learner and asks them to introduce themselves.', 'lt' => 'Gabija susitinka su mokiniu ir paprašo prisistatyti.'],
                        ],
                        'lines' => [
                            ['target_text' => 'Laba diena. Prašau prisistatykite.', 'support_translation' => 'Good day. Please introduce yourself.', 'trigger_goal' => null],
                            ['target_text' => 'Sveiki. Pasakykite, kas jūs esate.', 'support_translation' => 'Hello. Say who you are.', 'trigger_goal' => null],
                            ['target_text' => 'Malonu susipažinti.', 'support_translation' => 'Nice to meet you.', 'trigger_goal' => 'short-intro', 'priority' => 100],
                            ['target_text' => 'Puiku. Tai aiški pradžia.', 'support_translation' => 'Great. That is a clear start.', 'trigger_goal' => 'short-intro', 'priority' => 100],
                        ],
                        'goals' => [
                            [
                                'slug' => 'short-intro',
                                'label' => 'Introduce yourself',
                                'intent' => 'The learner greets and gives their name, and may add a simple identity detail.',
                                'example' => 'Laba diena. Mano vardas Deividas.',
                                'next' => 'klausimas',
                                'translations' => [
                                    'label' => ['en' => 'Introduce yourself', 'lt' => 'Prisistatykite'],
                                    'intent' => ['en' => 'The learner greets and gives their name, and may add a simple identity detail.', 'lt' => 'Mokinys pasisveikina, pasako savo vardą ir gali pridėti paprastą tapatybės detalę.'],
                                ],
                            ],
                        ],
                    ],
                    [
                        'slug' => 'klausimas',
                        'title' => 'Answer a question',
                        'setting' => 'Gabija asks one follow-up identity question.',
                        'translations' => [
                            'title' => ['en' => 'Answer a question', 'lt' => 'Atsakykite į klausimą'],
                            'setting' => ['en' => 'Gabija asks one follow-up identity question.', 'lt' => 'Gabija užduoda vieną papildomą klausimą apie tapatybę.'],
                        ],
                        'lines' => [
                            ['target_text' => 'Ar jūs esate studentas?', 'support_translation' => 'Are you a student?', 'trigger_goal' => null],
                            ['target_text' => 'Iš kur jūs esate?', 'support_translation' => 'Where are you from?', 'trigger_goal' => null],
                            ['target_text' => 'Labai gerai. Susipažinome!', 'support_translation' => 'Very good. We got acquainted!', 'trigger_goal' => 'answer-follow-up', 'priority' => 100],
                            ['target_text' => 'Puiku. Dabar galime tęsti mokymąsi.', 'support_translation' => 'Great. Now we can continue learning.', 'trigger_goal' => 'answer-follow-up', 'priority' => 100],
                        ],
                        'goals' => [
                            [
                                'slug' => 'answer-follow-up',
                                'label' => 'Answer the question',
                                'intent' => 'The learner answers the current simple identity question, such as whether they are a student or where they are from.',
                                'example' => 'Taip, aš esu studentas.',
                                'next' => null,
                                'translations' => [
                                    'label' => ['en' => 'Answer the question', 'lt' => 'Atsakykite į klausimą'],
                                    'intent' => ['en' => 'The learner answers the current simple identity question, such as whether they are a student or where they are from.', 'lt' => 'Mokinys atsako į paprastą klausimą apie tapatybę, pavyzdžiui, ar yra studentas arba iš kur yra.'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
