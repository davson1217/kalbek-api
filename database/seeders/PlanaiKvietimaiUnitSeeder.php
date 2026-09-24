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

class PlanaiKvietimaiUnitSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $language = Language::query()->where('code', 'lt')->firstOrFail();
            $character = Character::query()->where('slug', 'gabija')->firstOrFail();
            $unit = Unit::query()->updateOrCreate(
                ['slug' => 'planai-ir-kvietimai'],
                [
                    'language_id' => $language->id,
                    'title' => 'Planai ir kvietimai',
                    'description' => 'A practical unit for inviting someone, accepting or declining, and agreeing on a simple time and place.',
                    'cefr_level' => 'a1',
                    'status' => ContentStatus::Published,
                    'sort_order' => 140,
                    'published_at' => now(),
                ],
            );

            $this->syncTranslations($unit, [
                'title' => ['en' => 'Plans and Invitations', 'lt' => 'Planai ir kvietimai'],
                'description' => [
                    'en' => 'A practical unit for inviting someone, accepting or declining, and agreeing on a simple time and place.',
                    'lt' => 'Praktiškas skyrius apie kvietimą, sutikimą arba atsisakymą ir paprasto laiko bei vietos suderinimą.',
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

    private function deleteRemovedScenes(Scenario $scenario, array $sceneData): void
    {
        $scenario->scenes()
            ->whereNotIn('slug', collect($sceneData)->pluck('slug')->all())
            ->get()
            ->each
            ->delete();
    }

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
                'slug' => 'ar-nori-eiti',
                'title' => 'Ar nori eiti?',
                'title_en' => 'Do You Want to Go?',
                'subtitle' => 'Invite someone simply',
                'subtitle_lt' => 'Paprastai pakvieskite žmogų',
                'description' => 'Invite someone to go to a cafe, cinema, park, or town.',
                'description_lt' => 'Pakvieskite žmogų eiti į kavinę, kiną, parką arba miestą.',
                'emoji' => '🤝',
                'tone' => 'primary',
                'sort_order' => 141,
                'scene_slug' => 'kvietimas',
                'goal_slug' => 'invite-someone',
                'goal_label' => 'Invite someone',
                'goal_label_lt' => 'Pakvieskite žmogų',
                'goal_intent' => 'The learner invites someone to go somewhere simple.',
                'goal_intent_lt' => 'Mokinys pakviečia žmogų eiti į paprastą vietą.',
                'example' => 'Ar nori eiti į kavinę?',
                'openings' => ['Kur norite pakviesti?', 'Pakvieskite mane kur nors.'],
                'replies' => ['Ačiū už kvietimą.', 'Gerai. Kvietimas aiškus.'],
                'note' => [
                    'en' => [
                        'This lesson starts invitations with one simple question.',
                        'Useful phrases:',
                        '- Ar nori eiti? = Do you want to go?',
                        '- Ar nori eiti į kavinę? = Do you want to go to a cafe?',
                        '- Gal einame į parką? = Maybe we go to the park?',
                        '- Eime į miestą. = Let’s go to town.',
                        'Focus: ar starts a yes/no question.',
                    ],
                    'lt' => [
                        'Ši pamoka pradeda kvietimus vienu paprastu klausimu.',
                        'Naudingos frazės:',
                        '- Ar nori eiti?',
                        '- Ar nori eiti į kavinę?',
                        '- Gal einame į parką?',
                        '- Eime į miestą.',
                        'Svarbu: ar pradeda taip/ne klausimą.',
                    ],
                ],
            ]),
            $this->scenario([
                'slug' => 'taip-arba-ne',
                'title' => 'Taip arba ne',
                'title_en' => 'Yes or No',
                'subtitle' => 'Accept or decline simply',
                'subtitle_lt' => 'Paprastai sutikite arba atsisakykite',
                'description' => 'Say yes, no, or maybe to an invitation.',
                'description_lt' => 'Pasakykite taip, ne arba gal į kvietimą.',
                'emoji' => '✅',
                'tone' => 'mint',
                'sort_order' => 142,
                'scene_slug' => 'atsakymas',
                'goal_slug' => 'answer-invitation',
                'goal_label' => 'Answer an invitation',
                'goal_label_lt' => 'Atsakykite į kvietimą',
                'goal_intent' => 'The learner accepts, declines, or gives a simple maybe response.',
                'goal_intent_lt' => 'Mokinys sutinka, atsisako arba paprastai pasako gal.',
                'example' => 'Taip, noriu.',
                'openings' => ['Ar norite eiti?', 'Ką atsakysite į kvietimą?'],
                'replies' => ['Gerai. Atsakymas aiškus.', 'Ačiū. Supratau atsakymą.'],
                'note' => [
                    'en' => [
                        'This lesson practises short answers to invitations.',
                        'Useful phrases:',
                        '- Taip, noriu. = Yes, I want to.',
                        '- Taip, galiu. = Yes, I can.',
                        '- Ne, negaliu. = No, I cannot.',
                        '- Gal vėliau. = Maybe later.',
                        'A1 goal: a short clear answer is enough.',
                    ],
                    'lt' => [
                        'Ši pamoka praktikuoja trumpus atsakymus į kvietimus.',
                        'Naudingos frazės:',
                        '- Taip, noriu.',
                        '- Taip, galiu.',
                        '- Ne, negaliu.',
                        '- Gal vėliau.',
                        'A1 tikslas: užtenka trumpo aiškaus atsakymo.',
                    ],
                ],
            ]),
            $this->scenario([
                'slug' => 'kada-susitinkame',
                'title' => 'Kada susitinkame?',
                'title_en' => 'When Do We Meet?',
                'subtitle' => 'Agree on a time',
                'subtitle_lt' => 'Susitarkite dėl laiko',
                'description' => 'Ask or say when to meet.',
                'description_lt' => 'Paklauskite arba pasakykite, kada susitikti.',
                'emoji' => '🕒',
                'tone' => 'sky',
                'sort_order' => 143,
                'scene_slug' => 'laikas',
                'goal_slug' => 'agree-time',
                'goal_label' => 'Agree on a time',
                'goal_label_lt' => 'Susitarkite dėl laiko',
                'goal_intent' => 'The learner asks or says a simple meeting time.',
                'goal_intent_lt' => 'Mokinys paklausia arba pasako paprastą susitikimo laiką.',
                'example' => 'Susitinkame šeštą valandą.',
                'openings' => ['Kada susitinkame?', 'Koks laikas tinka?'],
                'replies' => ['Gerai. Laikas tinka.', 'Puiku. Laikas aiškus.'],
                'note' => [
                    'en' => [
                        'This lesson turns an invitation into a plan.',
                        'Useful phrases:',
                        '- Kada susitinkame? = When do we meet?',
                        '- Susitinkame šeštą valandą. = We meet at six.',
                        '- Rytoj vakare. = Tomorrow evening.',
                        '- Šeštadienį. = On Saturday.',
                        'Focus: susitinkame means we meet.',
                    ],
                    'lt' => [
                        'Ši pamoka paverčia kvietimą planu.',
                        'Naudingos frazės:',
                        '- Kada susitinkame?',
                        '- Susitinkame šeštą valandą.',
                        '- Rytoj vakare.',
                        '- Šeštadienį.',
                        'Svarbu: susitinkame reiškia we meet.',
                    ],
                ],
            ]),
            $this->scenario([
                'slug' => 'kur-susitinkame',
                'title' => 'Kur susitinkame?',
                'title_en' => 'Where Do We Meet?',
                'subtitle' => 'Agree on a place',
                'subtitle_lt' => 'Susitarkite dėl vietos',
                'description' => 'Ask or say where to meet.',
                'description_lt' => 'Paklauskite arba pasakykite, kur susitikti.',
                'emoji' => '📍',
                'tone' => 'amber',
                'sort_order' => 144,
                'scene_slug' => 'vieta',
                'goal_slug' => 'agree-place',
                'goal_label' => 'Agree on a place',
                'goal_label_lt' => 'Susitarkite dėl vietos',
                'goal_intent' => 'The learner asks or says a simple meeting place.',
                'goal_intent_lt' => 'Mokinys paklausia arba pasako paprastą susitikimo vietą.',
                'example' => 'Susitinkame prie kavinės.',
                'openings' => ['Kur susitinkame?', 'Kokia vieta tinka?'],
                'replies' => ['Gerai. Vieta tinka.', 'Puiku. Vieta aiški.'],
                'note' => [
                    'en' => [
                        'This lesson practises agreeing on a place.',
                        'Useful phrases:',
                        '- Kur susitinkame? = Where do we meet?',
                        '- Susitinkame prie kavinės. = We meet near the cafe.',
                        '- Susitinkame parke. = We meet in the park.',
                        '- Prie stoties. = Near the station.',
                        'Focus: prie means near or by.',
                    ],
                    'lt' => [
                        'Ši pamoka praktikuoja susitarimą dėl vietos.',
                        'Naudingos frazės:',
                        '- Kur susitinkame?',
                        '- Susitinkame prie kavinės.',
                        '- Susitinkame parke.',
                        '- Prie stoties.',
                        'Svarbu: prie reiškia near arba by.',
                    ],
                ],
            ]),
            $this->scenario([
                'slug' => 'atsiprasau-negaliu',
                'title' => 'Atsiprašau, negaliu',
                'title_en' => 'Sorry, I Cannot',
                'subtitle' => 'Decline politely',
                'subtitle_lt' => 'Mandagiai atsisakykite',
                'description' => 'Decline an invitation politely and give a simple reason.',
                'description_lt' => 'Mandagiai atsisakykite kvietimo ir pasakykite paprastą priežastį.',
                'emoji' => '🙏',
                'tone' => 'berry',
                'sort_order' => 145,
                'scene_slug' => 'atsisakymas',
                'goal_slug' => 'decline-politely',
                'goal_label' => 'Decline politely',
                'goal_label_lt' => 'Mandagiai atsisakykite',
                'goal_intent' => 'The learner politely declines an invitation and may give a short reason.',
                'goal_intent_lt' => 'Mokinys mandagiai atsisako kvietimo ir gali pasakyti trumpą priežastį.',
                'example' => 'Atsiprašau, negaliu. Neturiu laiko.',
                'openings' => ['Ar galite ateiti?', 'Ką sakote, jeigu negalite?'],
                'replies' => ['Viskas gerai. Ačiū, kad pasakėte.', 'Suprantu. Gal kitą kartą.'],
                'note' => [
                    'en' => [
                        'This lesson practises a polite no.',
                        'Useful phrases:',
                        '- Atsiprašau, negaliu. = Sorry, I cannot.',
                        '- Neturiu laiko. = I do not have time.',
                        '- Šiandien negaliu. = I cannot today.',
                        '- Gal kitą kartą. = Maybe another time.',
                        'Focus: negaliu means I cannot.',
                    ],
                    'lt' => [
                        'Ši pamoka praktikuoja mandagų ne.',
                        'Naudingos frazės:',
                        '- Atsiprašau, negaliu.',
                        '- Neturiu laiko.',
                        '- Šiandien negaliu.',
                        '- Gal kitą kartą.',
                        'Svarbu: negaliu reiškia I cannot.',
                    ],
                ],
            ]),
            [
                'slug' => 'susitikimo-planas',
                'title' => 'Susitikimo planas',
                'subtitle' => 'Make a simple plan',
                'description' => 'Invite someone, answer, agree on time and place, and finish politely.',
                'emoji' => '🗓️',
                'tone' => 'primary',
                'sort_order' => 146,
                'start_scene' => 'kvietimas',
                'translations' => [
                    'title' => ['en' => 'Meeting Plan', 'lt' => 'Susitikimo planas'],
                    'subtitle' => ['en' => 'Make a simple plan', 'lt' => 'Sudarykite paprastą planą'],
                    'description' => ['en' => 'Invite someone, answer, agree on time and place, and finish politely.', 'lt' => 'Pakvieskite žmogų, atsakykite, susitarkite dėl laiko bei vietos ir mandagiai užbaikite.'],
                ],
                'note' => [
                    'title' => 'Before: Meeting Plan',
                    'body' => implode("\n\n", [
                        'This capstone combines invitations and simple planning.',
                        'Useful flow:',
                        '- Ar nori eiti į kavinę? = Do you want to go to a cafe?',
                        '- Taip, galiu. = Yes, I can.',
                        '- Kada susitinkame? = When do we meet?',
                        '- Susitinkame prie kavinės. = We meet near the cafe.',
                        'Goal: make a short plan with time and place.',
                    ]),
                    'translations' => [
                        'title' => ['en' => 'Before: Meeting Plan', 'lt' => 'Prieš scenarijų: Susitikimo planas'],
                        'body' => ['en' => implode("\n\n", [
                            'This capstone combines invitations and simple planning.',
                            'Useful flow:',
                            '- Ar nori eiti į kavinę? = Do you want to go to a cafe?',
                            '- Taip, galiu. = Yes, I can.',
                            '- Kada susitinkame? = When do we meet?',
                            '- Susitinkame prie kavinės. = We meet near the cafe.',
                            'Goal: make a short plan with time and place.',
                        ]), 'lt' => implode("\n\n", [
                            'Šis pakartojimo scenarijus sujungia kvietimus ir paprastą planavimą.',
                            'Naudinga seka:',
                            '- Ar nori eiti į kavinę?',
                            '- Taip, galiu.',
                            '- Kada susitinkame?',
                            '- Susitinkame prie kavinės.',
                            'Tikslas: sudarykite trumpą planą su laiku ir vieta.',
                        ])],
                    ],
                ],
                'scenes' => [
                    $this->capstoneScene('kvietimas', 'Invitation', 'Kvietimas', 'Gabija waits for a simple invitation.', 'Gabija laukia paprasto kvietimo.', ['Kur norite pakviesti?', 'Pakvieskite mane kur nors.'], ['Ačiū už kvietimą.', 'Gerai. Kvietimas aiškus.'], 'capstone-invite', 'Invite someone', 'Pakvieskite žmogų', 'The learner invites someone to go somewhere simple.', 'Mokinys pakviečia žmogų eiti į paprastą vietą.', 'Ar nori eiti į kavinę?', 'atsakymas'),
                    $this->capstoneScene('atsakymas', 'Answer', 'Atsakymas', 'Gabija asks for a yes, no, or maybe answer.', 'Gabija prašo atsakymo taip, ne arba gal.', ['Ką atsakysite?', 'Ar galite eiti?'], ['Gerai. Atsakymas aiškus.', 'Ačiū. Supratau.'], 'capstone-answer', 'Answer the invitation', 'Atsakykite į kvietimą', 'The learner accepts, declines, or gives a simple maybe response.', 'Mokinys sutinka, atsisako arba paprastai pasako gal.', 'Taip, galiu.', 'laikas'),
                    $this->capstoneScene('laikas', 'Time', 'Laikas', 'Gabija asks when to meet.', 'Gabija klausia, kada susitikti.', ['Kada susitinkame?', 'Koks laikas tinka?'], ['Gerai. Laikas tinka.', 'Puiku. Laikas aiškus.'], 'capstone-meeting-time', 'Agree on time', 'Susitarkite dėl laiko', 'The learner says or asks a simple meeting time.', 'Mokinys pasako arba paklausia paprasto susitikimo laiko.', 'Susitinkame šeštą valandą.', 'vieta'),
                    $this->capstoneScene('vieta', 'Place', 'Vieta', 'Gabija asks where to meet.', 'Gabija klausia, kur susitikti.', ['Kur susitinkame?', 'Kokia vieta tinka?'], ['Gerai. Vieta tinka.', 'Puiku. Planas aiškus.'], 'capstone-meeting-place', 'Agree on place', 'Susitarkite dėl vietos', 'The learner says or asks a simple meeting place.', 'Mokinys pasako arba paklausia paprastos susitikimo vietos.', 'Susitinkame prie kavinės.', null),
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
            'Kur norite pakviesti?' => 'Where do you want to invite someone?',
            'Pakvieskite mane kur nors.' => 'Invite me somewhere.',
            'Ačiū už kvietimą.' => 'Thank you for the invitation.',
            'Gerai. Kvietimas aiškus.' => 'Good. The invitation is clear.',
            'Ar norite eiti?' => 'Do you want to go?',
            'Ką atsakysite į kvietimą?' => 'What will you answer to the invitation?',
            'Gerai. Atsakymas aiškus.' => 'Good. The answer is clear.',
            'Ačiū. Supratau atsakymą.' => 'Thank you. I understood the answer.',
            'Kada susitinkame?' => 'When do we meet?',
            'Koks laikas tinka?' => 'What time works?',
            'Gerai. Laikas tinka.' => 'Good. The time works.',
            'Puiku. Laikas aiškus.' => 'Great. The time is clear.',
            'Kur susitinkame?' => 'Where do we meet?',
            'Kokia vieta tinka?' => 'What place works?',
            'Gerai. Vieta tinka.' => 'Good. The place works.',
            'Puiku. Vieta aiški.' => 'Great. The place is clear.',
            'Ar galite ateiti?' => 'Can you come?',
            'Ką sakote, jeigu negalite?' => 'What do you say if you cannot?',
            'Viskas gerai. Ačiū, kad pasakėte.' => 'Everything is fine. Thank you for saying it.',
            'Suprantu. Gal kitą kartą.' => 'I understand. Maybe another time.',
            'Ką atsakysite?' => 'What will you answer?',
            'Ar galite eiti?' => 'Can you go?',
            'Ačiū. Supratau.' => 'Thank you. I understand.',
            'Puiku. Planas aiškus.' => 'Great. The plan is clear.',
            default => $line,
        };
    }
}
