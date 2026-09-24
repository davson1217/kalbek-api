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

class KlasejeMokantisUnitSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $language = Language::query()->where('code', 'lt')->firstOrFail();
            $character = Character::query()->where('slug', 'gabija')->firstOrFail();
            $unit = Unit::query()->updateOrCreate(
                ['slug' => 'klaseje-ir-mokantis'],
                [
                    'language_id' => $language->id,
                    'title' => 'Klasėje ir mokantis',
                    'description' => 'A practical unit for classroom language, asking for repetition, saying what you understand, and asking for help.',
                    'cefr_level' => 'a1',
                    'status' => ContentStatus::Published,
                    'sort_order' => 120,
                    'published_at' => now(),
                ],
            );

            $this->syncTranslations($unit, [
                'title' => ['en' => 'In Class and Learning', 'lt' => 'Klasėje ir mokantis'],
                'description' => [
                    'en' => 'A practical unit for classroom language, asking for repetition, saying what you understand, and asking for help.',
                    'lt' => 'Praktiškas skyrius apie klasės kalbą, pakartojimo prašymą, supratimo pasakymą ir pagalbos prašymą.',
                ],
            ]);

            $scenarios = $this->scenarios();
            $this->deleteRemovedScenarios($unit, $scenarios);

            foreach ($scenarios as $scenarioData) {
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
                        'sort_order' => $scenarioData['sort_order'],
                        'published_at' => now(),
                    ],
                );

                $this->syncTranslations($scenario, $scenarioData['translations']);
                $this->upsertNote($scenario, $scenarioData['note']);
                $this->deleteRemovedScenes($scenario, $scenarioData['scenes']);
                $scenes = $this->upsertScenes($scenario, $scenarioData['scenes']);
                $this->replaceSceneContent($scenes, $scenarioData['scenes']);
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
            $sceneModel = $scenes->get($scene['slug']);

            foreach ($scene['goals'] as $index => $goal) {
                $goalModel = $sceneModel->goals()->updateOrCreate(
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

            $sceneModel->goals()->whereNotIn('slug', collect($scene['goals'])->pluck('slug')->all())->delete();
        }

        $goals = Goal::query()->whereIn('scene_id', $scenes->pluck('id'))->get()->keyBy('slug');

        foreach ($sceneData as $scene) {
            $sceneModel = $scenes->get($scene['slug']);
            $sceneModel->npcLines()->delete();
            $sceneModel->props()->delete();

            foreach ($scene['lines'] as $index => $line) {
                $npcLine = $sceneModel->npcLines()->create([
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

    /**
     * @param  array<int, array<string, mixed>>  $scenarioData
     */
    private function deleteRemovedScenarios(Unit $unit, array $scenarioData): void
    {
        $unit->scenarios()
            ->whereNotIn('slug', collect($scenarioData)->pluck('slug')->all())
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
                'slug' => 'pamokoje',
                'title' => 'Pamokoje',
                'title_en' => 'In a Lesson',
                'subtitle' => 'Say you are ready',
                'subtitle_lt' => 'Pasakykite, kad esate pasiruošę',
                'description' => 'Use simple classroom phrases at the start of a lesson.',
                'description_lt' => 'Naudokite paprastas klasės frazes pamokos pradžioje.',
                'emoji' => '📚',
                'tone' => 'primary',
                'sort_order' => 121,
                'scene_slug' => 'pradzia',
                'goal_slug' => 'say-ready',
                'goal_label' => 'Say you are ready',
                'goal_label_lt' => 'Pasakykite, kad esate pasiruošę',
                'goal_intent' => 'The learner says they are ready or greets the teacher at the start of a lesson.',
                'goal_intent_lt' => 'Mokinys pasako, kad yra pasiruošęs, arba pasisveikina su mokytoju pamokos pradžioje.',
                'example' => 'Aš pasiruošęs.',
                'openings' => ['Ar esate pasiruošę?', 'Pradedame pamoką. Ar pasiruošę?'],
                'replies' => ['Puiku. Pradedame.', 'Gerai. Galime pradėti.'],
                'note' => [
                    'en' => [
                        'This lesson starts classroom Lithuanian with very useful phrases.',
                        'Useful phrases:',
                        '- Aš pasiruošęs. = I am ready.',
                        '- Aš pasiruošusi. = I am ready.',
                        '- Galime pradėti. = We can start.',
                        '- Laba diena, mokytoja. = Good day, teacher.',
                        'Note: pasiruošęs is masculine; pasiruošusi is feminine.',
                    ],
                    'lt' => [
                        'Ši pamoka pradeda klasės lietuvių kalbą labai naudingomis frazėmis.',
                        'Naudingos frazės:',
                        '- Aš pasiruošęs.',
                        '- Aš pasiruošusi.',
                        '- Galime pradėti.',
                        '- Laba diena, mokytoja.',
                        'Pastaba: pasiruošęs yra vyriška forma, pasiruošusi - moteriška forma.',
                    ],
                ],
            ]),
            $this->scenario([
                'slug' => 'as-nesuprantu',
                'title' => 'Aš nesuprantu',
                'title_en' => 'I Do Not Understand',
                'subtitle' => 'Say what you understand',
                'subtitle_lt' => 'Pasakykite, ką suprantate',
                'description' => 'Say that you understand or do not understand.',
                'description_lt' => 'Pasakykite, kad suprantate arba nesuprantate.',
                'emoji' => '❓',
                'tone' => 'mint',
                'sort_order' => 122,
                'scene_slug' => 'supratimas',
                'goal_slug' => 'say-understanding',
                'goal_label' => 'Say if you understand',
                'goal_label_lt' => 'Pasakykite, ar suprantate',
                'goal_intent' => 'The learner says they understand or do not understand.',
                'goal_intent_lt' => 'Mokinys pasako, kad supranta arba nesupranta.',
                'example' => 'Aš nesuprantu.',
                'openings' => ['Ar suprantate?', 'Ar viskas aišku?'],
                'replies' => ['Gerai. Paaiškinsiu dar kartą.', 'Suprantu. Pakartokime lėtai.'],
                'note' => [
                    'en' => [
                        'This lesson gives the learner permission to ask for clarity.',
                        'Useful phrases:',
                        '- Aš suprantu. = I understand.',
                        '- Aš nesuprantu. = I do not understand.',
                        '- Taip, suprantu. = Yes, I understand.',
                        '- Ne, nesuprantu. = No, I do not understand.',
                        'Focus: ne- before a verb often makes it negative.',
                    ],
                    'lt' => [
                        'Ši pamoka moko paprašyti aiškumo.',
                        'Naudingos frazės:',
                        '- Aš suprantu.',
                        '- Aš nesuprantu.',
                        '- Taip, suprantu.',
                        '- Ne, nesuprantu.',
                        'Svarbu: ne- prieš veiksmažodį dažnai rodo neiginį.',
                    ],
                ],
            ]),
            $this->scenario([
                'slug' => 'pakartokite-prasau',
                'title' => 'Pakartokite, prašau',
                'title_en' => 'Please Repeat',
                'subtitle' => 'Ask someone to repeat',
                'subtitle_lt' => 'Paprašykite pakartoti',
                'description' => 'Ask the teacher to repeat slowly or one more time.',
                'description_lt' => 'Paprašykite mokytojo pakartoti lėtai arba dar kartą.',
                'emoji' => '🔁',
                'tone' => 'sky',
                'sort_order' => 123,
                'scene_slug' => 'pakartojimas',
                'goal_slug' => 'ask-repeat',
                'goal_label' => 'Ask for repetition',
                'goal_label_lt' => 'Paprašykite pakartoti',
                'goal_intent' => 'The learner asks someone to repeat or speak slowly.',
                'goal_intent_lt' => 'Mokinys paprašo pakartoti arba kalbėti lėtai.',
                'example' => 'Pakartokite, prašau.',
                'openings' => ['Ar galite pakartoti?', 'Ką sakote, kai reikia pakartoti?'],
                'replies' => ['Žinoma. Pakartosiu.', 'Gerai. Pasakysiu lėčiau.'],
                'note' => [
                    'en' => [
                        'This lesson practises a survival phrase for listening.',
                        'Useful phrases:',
                        '- Pakartokite, prašau. = Please repeat.',
                        '- Dar kartą, prašau. = One more time, please.',
                        '- Lėčiau, prašau. = More slowly, please.',
                        '- Kalbėkite lėčiau, prašau. = Speak more slowly, please.',
                        'A1 goal: use one polite request.',
                    ],
                    'lt' => [
                        'Ši pamoka praktikuoja labai reikalingą klausymo frazę.',
                        'Naudingos frazės:',
                        '- Pakartokite, prašau.',
                        '- Dar kartą, prašau.',
                        '- Lėčiau, prašau.',
                        '- Kalbėkite lėčiau, prašau.',
                        'A1 tikslas: naudokite vieną mandagų prašymą.',
                    ],
                ],
            ]),
            $this->scenario([
                'slug' => 'ka-reiskia',
                'title' => 'Ką reiškia?',
                'title_en' => 'What Does It Mean?',
                'subtitle' => 'Ask the meaning of a word',
                'subtitle_lt' => 'Paklauskite žodžio reikšmės',
                'description' => 'Ask what a Lithuanian word means.',
                'description_lt' => 'Paklauskite, ką reiškia lietuviškas žodis.',
                'emoji' => '💬',
                'tone' => 'amber',
                'sort_order' => 124,
                'scene_slug' => 'reiksme',
                'goal_slug' => 'ask-meaning',
                'goal_label' => 'Ask what a word means',
                'goal_label_lt' => 'Paklauskite žodžio reikšmės',
                'goal_intent' => 'The learner asks what a word or phrase means.',
                'goal_intent_lt' => 'Mokinys paklausia, ką reiškia žodis arba frazė.',
                'example' => 'Ką reiškia šis žodis?',
                'openings' => ['Ar žinote šį žodį?', 'Ko norite paklausti apie žodį?'],
                'replies' => ['Gerai. Paaiškinsiu reikšmę.', 'Žinoma. Galiu paaiškinti.'],
                'note' => [
                    'en' => [
                        'This lesson teaches one of the most useful learner questions.',
                        'Useful phrases:',
                        '- Ką reiškia? = What does it mean?',
                        '- Ką reiškia šis žodis? = What does this word mean?',
                        '- Kaip pasakyti lietuviškai? = How do you say it in Lithuanian?',
                        '- Nežinau šio žodžio. = I do not know this word.',
                        'Focus: ką means what.',
                    ],
                    'lt' => [
                        'Ši pamoka moko vieno naudingiausių mokymosi klausimų.',
                        'Naudingos frazės:',
                        '- Ką reiškia?',
                        '- Ką reiškia šis žodis?',
                        '- Kaip pasakyti lietuviškai?',
                        '- Nežinau šio žodžio.',
                        'Svarbu: ką reiškia what.',
                    ],
                ],
            ]),
            $this->scenario([
                'slug' => 'ar-galite-padeti',
                'title' => 'Ar galite padėti?',
                'title_en' => 'Can You Help?',
                'subtitle' => 'Ask for learning help',
                'subtitle_lt' => 'Paprašykite pagalbos mokantis',
                'description' => 'Ask for help politely during a lesson.',
                'description_lt' => 'Mandagiai paprašykite pagalbos per pamoką.',
                'emoji' => '🙋',
                'tone' => 'berry',
                'sort_order' => 125,
                'scene_slug' => 'pagalba',
                'goal_slug' => 'ask-learning-help',
                'goal_label' => 'Ask for help',
                'goal_label_lt' => 'Paprašykite pagalbos',
                'goal_intent' => 'The learner asks the teacher for help with a task, word, or sentence.',
                'goal_intent_lt' => 'Mokinys paprašo mokytojo pagalbos su užduotimi, žodžiu arba sakiniu.',
                'example' => 'Ar galite padėti?',
                'openings' => ['Ar reikia pagalbos?', 'Kaip galiu padėti?'],
                'replies' => ['Žinoma. Padėsiu.', 'Gerai. Pažiūrėkime kartu.'],
                'note' => [
                    'en' => [
                        'This lesson practises asking for help politely.',
                        'Useful phrases:',
                        '- Ar galite padėti? = Can you help?',
                        '- Padėkite, prašau. = Help, please.',
                        '- Man reikia pagalbos. = I need help.',
                        '- Su šiuo sakiniu. = With this sentence.',
                        'Focus: ar starts a yes/no question.',
                    ],
                    'lt' => [
                        'Ši pamoka praktikuoja mandagų pagalbos prašymą.',
                        'Naudingos frazės:',
                        '- Ar galite padėti?',
                        '- Padėkite, prašau.',
                        '- Man reikia pagalbos.',
                        '- Su šiuo sakiniu.',
                        'Svarbu: ar pradeda taip/ne klausimą.',
                    ],
                ],
            ]),
            [
                'slug' => 'mokymosi-pokalbis',
                'title' => 'Mokymosi pokalbis',
                'subtitle' => 'Ask for clarity in a lesson',
                'description' => 'Say you do not understand, ask for repetition, ask what a word means, and thank the teacher.',
                'emoji' => '📝',
                'tone' => 'primary',
                'sort_order' => 126,
                'start_scene' => 'supratimas',
                'translations' => [
                    'title' => ['en' => 'Learning Conversation', 'lt' => 'Mokymosi pokalbis'],
                    'subtitle' => ['en' => 'Ask for clarity in a lesson', 'lt' => 'Paprašykite aiškumo pamokoje'],
                    'description' => ['en' => 'Say you do not understand, ask for repetition, ask what a word means, and thank the teacher.', 'lt' => 'Pasakykite, kad nesuprantate, paprašykite pakartoti, paklauskite žodžio reikšmės ir padėkokite mokytojui.'],
                ],
                'note' => [
                    'title' => 'Before: Learning Conversation',
                    'body' => implode("\n\n", [
                        'This capstone combines classroom survival phrases.',
                        'Useful flow:',
                        '- Aš nesuprantu. = I do not understand.',
                        '- Pakartokite, prašau. = Please repeat.',
                        '- Ką reiškia šis žodis? = What does this word mean?',
                        '- Ačiū už pagalbą. = Thank you for the help.',
                        'Goal: keep learning even when you need clarification.',
                    ]),
                    'translations' => [
                        'title' => ['en' => 'Before: Learning Conversation', 'lt' => 'Prieš scenarijų: Mokymosi pokalbis'],
                        'body' => ['en' => implode("\n\n", [
                            'This capstone combines classroom survival phrases.',
                            'Useful flow:',
                            '- Aš nesuprantu. = I do not understand.',
                            '- Pakartokite, prašau. = Please repeat.',
                            '- Ką reiškia šis žodis? = What does this word mean?',
                            '- Ačiū už pagalbą. = Thank you for the help.',
                            'Goal: keep learning even when you need clarification.',
                        ]), 'lt' => implode("\n\n", [
                            'Šis pakartojimo scenarijus sujungia klasės išlikimo frazes.',
                            'Naudinga seka:',
                            '- Aš nesuprantu.',
                            '- Pakartokite, prašau.',
                            '- Ką reiškia šis žodis?',
                            '- Ačiū už pagalbą.',
                            'Tikslas: tęskite mokymąsi net tada, kai reikia aiškumo.',
                        ])],
                    ],
                ],
                'scenes' => [
                    $this->capstoneScene('supratimas', 'Understanding', 'Supratimas', 'Gabija asks if everything is clear.', 'Gabija klausia, ar viskas aišku.', ['Ar viskas aišku?', 'Ar suprantate?'], ['Gerai. Pakartokime.', 'Suprantu. Padėsiu.'], 'capstone-not-understand', 'Say you do not understand', 'Pasakykite, kad nesuprantate', 'The learner says that they do not understand.', 'Mokinys pasako, kad nesupranta.', 'Aš nesuprantu.', 'pakartojimas'),
                    $this->capstoneScene('pakartojimas', 'Repetition', 'Pakartojimas', 'Gabija waits for a repetition request.', 'Gabija laukia pakartojimo prašymo.', ['Ko jums reikia?', 'Kaip galiu padėti?'], ['Žinoma. Pakartosiu.', 'Gerai. Pasakysiu lėčiau.'], 'capstone-repeat', 'Ask for repetition', 'Paprašykite pakartoti', 'The learner asks for repetition or slower speech.', 'Mokinys paprašo pakartoti arba kalbėti lėčiau.', 'Pakartokite, prašau.', 'reiksme'),
                    $this->capstoneScene('reiksme', 'Meaning', 'Reikšmė', 'Gabija asks what else the learner wants to know.', 'Gabija klausia, ką dar mokinys nori sužinoti.', ['Ką dar norite sužinoti?', 'Apie ką norite paklausti?'], ['Žinoma. Paaiškinsiu.', 'Gerai. Ačiū už klausimą.'], 'capstone-meaning', 'Ask what a word means', 'Paklauskite žodžio reikšmės', 'The learner asks what a word or phrase means.', 'Mokinys paklausia, ką reiškia žodis arba frazė.', 'Ką reiškia šis žodis?', null),
                ],
            ],
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
     * @return array<string, mixed>
     */
    private function capstoneScene(string $slug, string $title, string $titleLt, string $setting, string $settingLt, array $openings, array $replies, string $goalSlug, string $goalLabel, string $goalLabelLt, string $intent, string $intentLt, string $example, ?string $next): array
    {
        return [
            'slug' => $slug,
            'title' => $title,
            'setting' => $setting,
            'translations' => [
                'title' => ['en' => $title, 'lt' => $titleLt],
                'setting' => ['en' => $setting, 'lt' => $settingLt],
            ],
            'lines' => $this->lines($openings, $replies, $goalSlug),
            'goals' => [[
                'slug' => $goalSlug,
                'label' => $goalLabel,
                'intent' => $intent,
                'example' => $example,
                'next' => $next,
                'translations' => [
                    'label' => ['en' => $goalLabel, 'lt' => $goalLabelLt],
                    'intent' => ['en' => $intent, 'lt' => $intentLt],
                ],
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
            'Ar esate pasiruošę?' => 'Are you ready?',
            'Pradedame pamoką. Ar pasiruošę?' => 'We are starting the lesson. Are you ready?',
            'Puiku. Pradedame.' => 'Great. We are starting.',
            'Gerai. Galime pradėti.' => 'Good. We can start.',
            'Ar suprantate?' => 'Do you understand?',
            'Ar viskas aišku?' => 'Is everything clear?',
            'Gerai. Paaiškinsiu dar kartą.' => 'Good. I will explain one more time.',
            'Suprantu. Pakartokime lėtai.' => 'I understand. Let’s repeat slowly.',
            'Ar galite pakartoti?' => 'Can you repeat?',
            'Ką sakote, kai reikia pakartoti?' => 'What do you say when repetition is needed?',
            'Žinoma. Pakartosiu.' => 'Of course. I will repeat.',
            'Gerai. Pasakysiu lėčiau.' => 'Good. I will say it more slowly.',
            'Ar žinote šį žodį?' => 'Do you know this word?',
            'Ko norite paklausti apie žodį?' => 'What do you want to ask about the word?',
            'Gerai. Paaiškinsiu reikšmę.' => 'Good. I will explain the meaning.',
            'Žinoma. Galiu paaiškinti.' => 'Of course. I can explain.',
            'Ar reikia pagalbos?' => 'Is help needed?',
            'Kaip galiu padėti?' => 'How can I help?',
            'Žinoma. Padėsiu.' => 'Of course. I will help.',
            'Gerai. Pažiūrėkime kartu.' => 'Good. Let’s look together.',
            'Gerai. Pakartokime.' => 'Good. Let’s repeat.',
            'Suprantu. Padėsiu.' => 'I understand. I will help.',
            'Ko jums reikia?' => 'What do you need?',
            'Ką dar norite sužinoti?' => 'What else do you want to know?',
            'Apie ką norite paklausti?' => 'What do you want to ask about?',
            'Žinoma. Paaiškinsiu.' => 'Of course. I will explain.',
            'Gerai. Ačiū už klausimą.' => 'Good. Thank you for the question.',
            default => $line,
        };
    }
}
