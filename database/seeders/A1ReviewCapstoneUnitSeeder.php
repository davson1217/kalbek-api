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

class A1ReviewCapstoneUnitSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $language = Language::query()->where('code', 'lt')->firstOrFail();
            $character = Character::query()->where('slug', 'gabija')->firstOrFail();
            $unit = Unit::query()->updateOrCreate(
                ['slug' => 'a1-kartojimas'],
                [
                    'language_id' => $language->id,
                    'title' => 'A1 kartojimas',
                    'description' => 'A final A1 review unit that combines introductions, daily needs, food, directions, time, health, plans, and travel.',
                    'cefr_level' => 'a1',
                    'status' => ContentStatus::Published,
                    'sort_order' => 170,
                    'published_at' => now(),
                ],
            );

            $this->syncTranslations($unit, [
                'title' => ['en' => 'A1 Review', 'lt' => 'A1 kartojimas'],
                'description' => [
                    'en' => 'A final A1 review unit that combines introductions, daily needs, food, directions, time, health, plans, and travel.',
                    'lt' => 'Baigiamasis A1 kartojimo skyrius, jungiantis prisistatymą, kasdienius poreikius, maistą, kryptis, laiką, sveikatą, planus ir kelionę.',
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
            'estimated_minutes' => 3,
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
                        'accepted_phrases' => $goal['accepted_phrases'] ?? null,
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
                'slug' => 'a1-apie-save',
                'title' => 'Apie save',
                'title_en' => 'About Yourself',
                'subtitle' => 'Introduce yourself in simple sentences',
                'subtitle_lt' => 'Prisistatykite paprastais sakiniais',
                'description' => 'Review names, origin, role, family, and simple personal facts.',
                'description_lt' => 'Pakartokite vardą, kilmę, vaidmenį, šeimą ir paprastą informaciją apie save.',
                'emoji' => '👋',
                'tone' => 'primary',
                'sort_order' => 171,
                'scene_slug' => 'prisistatymas',
                'goal_slug' => 'review-introduce-self',
                'goal_label' => 'Introduce yourself',
                'goal_label_lt' => 'Prisistatykite',
                'goal_intent' => 'The learner gives a short self-introduction with name and one extra personal detail.',
                'goal_intent_lt' => 'Mokinys trumpai prisistato, pasako vardą ir vieną papildomą asmeninę detalę.',
                'example' => 'Mano vardas Tomas. Aš esu iš Kauno.',
                'accepted_phrases' => [
                    'Mano vardas Tomas.',
                    'Aš esu Tomas.',
                    'Aš esu iš Kauno.',
                    'Aš gyvenu Vilniuje.',
                    'Aš esu studentas.',
                    'Aš esu programuotojas.',
                ],
                'openings' => ['Trumpai prisistatykite.', 'Pasakykite, kas jūs esate.'],
                'replies' => ['Malonu susipažinti.', 'Puiku. Prisistatymas aiškus.'],
                'note' => [
                    'en' => [
                        'This review starts with the basics: name, origin, place, and role.',
                        'Useful phrases:',
                        '- Mano vardas ... = My name is ...',
                        '- Aš esu iš ... = I am from ...',
                        '- Aš gyvenu ... = I live ...',
                        '- Aš esu studentas / studentė. = I am a student.',
                        'Goal: say two short facts about yourself.',
                    ],
                    'lt' => [
                        'Šis kartojimas prasideda nuo pagrindų: vardo, kilmės, vietos ir vaidmens.',
                        'Naudingos frazės:',
                        '- Mano vardas ...',
                        '- Aš esu iš ...',
                        '- Aš gyvenu ...',
                        '- Aš esu studentas / studentė.',
                        'Tikslas: pasakykite du trumpus faktus apie save.',
                    ],
                ],
            ]),
            $this->scenario([
                'slug' => 'a1-kasdieniai-poreikiai',
                'title' => 'Kasdieniai poreikiai',
                'title_en' => 'Everyday Needs',
                'subtitle' => 'Say what you want, need, or can do',
                'subtitle_lt' => 'Pasakykite, ko norite, ko reikia arba ką galite',
                'description' => 'Review want, need, can, cannot, and have-to phrases.',
                'description_lt' => 'Pakartokite frazes noriu, reikia, galiu, negaliu ir turiu.',
                'emoji' => '✅',
                'tone' => 'mint',
                'sort_order' => 172,
                'scene_slug' => 'poreikis',
                'goal_slug' => 'review-everyday-need',
                'goal_label' => 'Say a need or ability',
                'goal_label_lt' => 'Pasakykite poreikį arba gebėjimą',
                'goal_intent' => 'The learner says a simple want, need, ability, limitation, or obligation.',
                'goal_intent_lt' => 'Mokinys pasako paprastą norą, poreikį, gebėjimą, ribojimą arba pareigą.',
                'example' => 'Man reikia pagalbos.',
                'accepted_phrases' => [
                    'Man reikia pagalbos.',
                    'Man reikia vandens.',
                    'Aš noriu kavos.',
                    'Aš galiu kalbėti lietuviškai.',
                    'Aš negaliu ateiti šiandien.',
                    'Aš turiu eiti.',
                ],
                'openings' => ['Ko jums reikia?', 'Pasakykite, ko norite arba ką galite.'],
                'replies' => ['Gerai. Poreikis aiškus.', 'Suprantu. Ačiū, kad pasakėte.'],
                'note' => [
                    'en' => [
                        'This review checks high-frequency A1 needs.',
                        'Useful phrases:',
                        '- Man reikia ... = I need ...',
                        '- Aš noriu ... = I want ...',
                        '- Aš galiu ... = I can ...',
                        '- Aš negaliu ... = I cannot ...',
                        '- Aš turiu ... = I have to ...',
                        'Goal: say one clear need, want, ability, or limitation.',
                    ],
                    'lt' => [
                        'Šis kartojimas tikrina dažnas A1 poreikių frazes.',
                        'Naudingos frazės:',
                        '- Man reikia ...',
                        '- Aš noriu ...',
                        '- Aš galiu ...',
                        '- Aš negaliu ...',
                        '- Aš turiu ...',
                        'Tikslas: aiškiai pasakykite vieną poreikį, norą, gebėjimą arba ribojimą.',
                    ],
                ],
            ]),
            $this->scenario([
                'slug' => 'a1-mieste',
                'title' => 'Mieste',
                'title_en' => 'In Town',
                'subtitle' => 'Ask where something is and understand a simple reply',
                'subtitle_lt' => 'Paklauskite, kur kas yra, ir supraskite paprastą atsakymą',
                'description' => 'Review places, directions, transport, tickets, and simple city help.',
                'description_lt' => 'Pakartokite vietas, kryptis, transportą, bilietus ir paprastą pagalbą mieste.',
                'emoji' => '🗺️',
                'tone' => 'sky',
                'sort_order' => 173,
                'scene_slug' => 'vieta',
                'goal_slug' => 'review-city-question',
                'goal_label' => 'Ask for a place or transport',
                'goal_label_lt' => 'Paklauskite apie vietą arba transportą',
                'goal_intent' => 'The learner asks where a place is, asks for a ticket, or asks about transport time/direction.',
                'goal_intent_lt' => 'Mokinys paklausia, kur yra vieta, paprašo bilieto arba paklausia apie transporto laiką ar kryptį.',
                'example' => 'Atsiprašau, kur yra stotis?',
                'accepted_phrases' => [
                    'Atsiprašau, kur yra stotis?',
                    'Kur yra vaistinė?',
                    'Kur yra tualetas?',
                    'Man reikia bilieto.',
                    'Kada išvyksta autobusas?',
                    'Kuris autobusas važiuoja į centrą?',
                ],
                'openings' => ['Kaip galiu padėti mieste?', 'Ko ieškote mieste?'],
                'replies' => ['Gerai. Klausimas aiškus.', 'Žinoma. Padėsiu rasti vietą.'],
                'note' => [
                    'en' => [
                        'This review combines places and transport.',
                        'Useful phrases:',
                        '- Kur yra ...? = Where is ...?',
                        '- Atsiprašau, kur yra stotis? = Excuse me, where is the station?',
                        '- Man reikia bilieto. = I need a ticket.',
                        '- Kada išvyksta autobusas? = When does the bus leave?',
                        'Goal: ask for one place, ticket, direction, or time.',
                    ],
                    'lt' => [
                        'Šis kartojimas sujungia vietas ir transportą.',
                        'Naudingos frazės:',
                        '- Kur yra ...?',
                        '- Atsiprašau, kur yra stotis?',
                        '- Man reikia bilieto.',
                        '- Kada išvyksta autobusas?',
                        'Tikslas: paklauskite apie vieną vietą, bilietą, kryptį arba laiką.',
                    ],
                ],
            ]),
            $this->scenario([
                'slug' => 'a1-paslaugos',
                'title' => 'Paslaugos',
                'title_en' => 'Services',
                'subtitle' => 'Handle a short shop, cafe, or pharmacy need',
                'subtitle_lt' => 'Išspręskite trumpą poreikį parduotuvėje, kavinėje arba vaistinėje',
                'description' => 'Review ordering, buying, price, payment, symptoms, and medicine.',
                'description_lt' => 'Pakartokite užsakymą, pirkimą, kainą, mokėjimą, simptomus ir vaistus.',
                'emoji' => '🧾',
                'tone' => 'amber',
                'sort_order' => 174,
                'scene_slug' => 'prasymas',
                'goal_slug' => 'review-service-request',
                'goal_label' => 'Make a simple service request',
                'goal_label_lt' => 'Pasakykite paprastą prašymą',
                'goal_intent' => 'The learner orders, asks for an item, asks price, pays, or says a simple health need.',
                'goal_intent_lt' => 'Mokinys užsisako, paprašo prekės, paklausia kainos, sumoka arba pasako paprastą sveikatos poreikį.',
                'example' => 'Norėčiau kavos, prašau.',
                'accepted_phrases' => [
                    'Norėčiau kavos, prašau.',
                    'Noriu vandens.',
                    'Kiek kainuoja?',
                    'Mokėsiu kortele.',
                    'Man skauda galvą.',
                    'Prašau vaistų nuo galvos skausmo.',
                ],
                'openings' => ['Kuo galiu padėti?', 'Ko pageidaujate?'],
                'replies' => ['Žinoma. Prašymas aiškus.', 'Gerai. Galiu padėti.'],
                'note' => [
                    'en' => [
                        'This review combines service situations.',
                        'Useful phrases:',
                        '- Norėčiau ..., prašau. = I would like ..., please.',
                        '- Kiek kainuoja? = How much does it cost?',
                        '- Mokėsiu kortele. = I will pay by card.',
                        '- Man skauda galvą. = My head hurts.',
                        'Goal: make one clear request in a cafe, shop, or pharmacy.',
                    ],
                    'lt' => [
                        'Šis kartojimas sujungia paslaugų situacijas.',
                        'Naudingos frazės:',
                        '- Norėčiau ..., prašau.',
                        '- Kiek kainuoja?',
                        '- Mokėsiu kortele.',
                        '- Man skauda galvą.',
                        'Tikslas: pasakykite vieną aiškų prašymą kavinėje, parduotuvėje arba vaistinėje.',
                    ],
                ],
            ]),
            $this->scenario([
                'slug' => 'a1-planai-ir-laikas',
                'title' => 'Planai ir laikas',
                'title_en' => 'Plans and Time',
                'subtitle' => 'Say when, where, and whether you can meet',
                'subtitle_lt' => 'Pasakykite, kada, kur ir ar galite susitikti',
                'description' => 'Review days, time, invitations, place, yes/no answers, and simple plans.',
                'description_lt' => 'Pakartokite dienas, laiką, kvietimus, vietą, taip/ne atsakymus ir paprastus planus.',
                'emoji' => '📅',
                'tone' => 'berry',
                'sort_order' => 175,
                'scene_slug' => 'planas',
                'goal_slug' => 'review-plan-time',
                'goal_label' => 'Make or answer a simple plan',
                'goal_label_lt' => 'Sudarykite arba atsakykite į paprastą planą',
                'goal_intent' => 'The learner accepts, declines, or gives a simple day, time, or meeting place.',
                'goal_intent_lt' => 'Mokinys sutinka, atsisako arba pasako paprastą dieną, laiką ar susitikimo vietą.',
                'example' => 'Susitinkame šeštą valandą prie kavinės.',
                'accepted_phrases' => [
                    'Taip, galiu.',
                    'Atsiprašau, negaliu.',
                    'Susitinkame rytoj.',
                    'Susitinkame šeštą valandą.',
                    'Susitinkame prie kavinės.',
                    'Ar nori eiti į kavinę?',
                ],
                'openings' => ['Kada ir kur susitinkame?', 'Ar galite susitikti?'],
                'replies' => ['Puiku. Planas aiškus.', 'Gerai. Supratau planą.'],
                'note' => [
                    'en' => [
                        'This review combines time and plans.',
                        'Useful phrases:',
                        '- Taip, galiu. = Yes, I can.',
                        '- Atsiprašau, negaliu. = Sorry, I cannot.',
                        '- Susitinkame rytoj. = We meet tomorrow.',
                        '- Susitinkame prie kavinės. = We meet near the cafe.',
                        'Goal: accept, decline, or give one simple meeting detail.',
                    ],
                    'lt' => [
                        'Šis kartojimas sujungia laiką ir planus.',
                        'Naudingos frazės:',
                        '- Taip, galiu.',
                        '- Atsiprašau, negaliu.',
                        '- Susitinkame rytoj.',
                        '- Susitinkame prie kavinės.',
                        'Tikslas: sutikite, atsisakykite arba pasakykite vieną paprastą susitikimo detalę.',
                    ],
                ],
            ]),
            [
                'slug' => 'a1-baigiamasis-pokalbis',
                'title' => 'A1 baigiamasis pokalbis',
                'subtitle' => 'Use A1 Lithuanian across real situations',
                'description' => 'Complete a short multi-step conversation that combines the whole A1 track.',
                'emoji' => '🎯',
                'tone' => 'primary',
                'sort_order' => 176,
                'start_scene' => 'pradzia',
                'translations' => [
                    'title' => ['en' => 'Final A1 Conversation', 'lt' => 'A1 baigiamasis pokalbis'],
                    'subtitle' => ['en' => 'Use A1 Lithuanian across real situations', 'lt' => 'Panaudokite A1 lietuvių kalbą realiose situacijose'],
                    'description' => ['en' => 'Complete a short multi-step conversation that combines the whole A1 track.', 'lt' => 'Atlikite trumpą kelių žingsnių pokalbį, kuris sujungia visą A1 kelią.'],
                ],
                'note' => [
                    'title' => 'Before: Final A1 Conversation',
                    'body' => implode("\n\n", [
                        'This is the final A1 speaking review.',
                        'You will move through several everyday moments:',
                        '- introduce yourself;',
                        '- ask for a place or transport help;',
                        '- make a cafe, shop, or pharmacy request;',
                        '- agree on a time or plan;',
                        '- close politely.',
                        'Use short, clear sentences. A perfect script is not required; the goal is understandable A1 communication.',
                    ]),
                    'translations' => [
                        'title' => ['en' => 'Before: Final A1 Conversation', 'lt' => 'Prieš scenarijų: A1 baigiamasis pokalbis'],
                        'body' => ['en' => implode("\n\n", [
                            'This is the final A1 speaking review.',
                            'You will move through several everyday moments:',
                            '- introduce yourself;',
                            '- ask for a place or transport help;',
                            '- make a cafe, shop, or pharmacy request;',
                            '- agree on a time or plan;',
                            '- close politely.',
                            'Use short, clear sentences. A perfect script is not required; the goal is understandable A1 communication.',
                        ]), 'lt' => implode("\n\n", [
                            'Tai baigiamasis A1 kalbėjimo kartojimas.',
                            'Jūs pereisite kelias kasdienes situacijas:',
                            '- prisistatysite;',
                            '- paklausite apie vietą arba transportą;',
                            '- pasakysite prašymą kavinėje, parduotuvėje arba vaistinėje;',
                            '- susitarsite dėl laiko arba plano;',
                            '- mandagiai užbaigsite pokalbį.',
                            'Kalbėkite trumpais ir aiškiais sakiniais. Tobulas scenarijus nebūtinas; tikslas yra suprantama A1 komunikacija.',
                        ])],
                    ],
                ],
                'scenes' => [
                    $this->capstoneScene('pradzia', 'Introduction', 'Prisistatymas', 'Gabija asks the learner to introduce themselves.', 'Gabija prašo mokinio prisistatyti.', ['Sveiki. Trumpai prisistatykite.', 'Laba diena. Kas jūs esate?'], ['Malonu susipažinti. Tęskime.', 'Puiku. Dabar pakalbėkime apie miestą.'], 'final-introduction', 'Introduce yourself', 'Prisistatykite', 'The learner gives their name and one simple personal detail.', 'Mokinys pasako vardą ir vieną paprastą asmeninę detalę.', 'Mano vardas Tomas. Aš esu iš Kauno.', 'miestas', ['Mano vardas Tomas.', 'Aš esu iš Kauno.', 'Aš gyvenu Vilniuje.', 'Aš esu studentas.']),
                    $this->capstoneScene('miestas', 'City help', 'Pagalba mieste', 'Gabija asks what help the learner needs in town.', 'Gabija klausia, kokios pagalbos mieste reikia.', ['Ko ieškote mieste?', 'Kur norite eiti?'], ['Gerai. Padėsiu jums.', 'Aišku. Vieta ar kelionė suprantama.'], 'final-city-help', 'Ask for city help', 'Paklauskite pagalbos mieste', 'The learner asks where a place is or asks one transport question.', 'Mokinys paklausia, kur yra vieta, arba užduoda vieną transporto klausimą.', 'Atsiprašau, kur yra stotis?', 'paslauga', ['Kur yra stotis?', 'Kur yra vaistinė?', 'Man reikia bilieto.', 'Kada išvyksta autobusas?']),
                    $this->capstoneScene('paslauga', 'Service request', 'Prašymas', 'Gabija is ready to help with a practical service need.', 'Gabija pasiruošusi padėti su praktiniu prašymu.', ['Kuo galiu padėti?', 'Ko jums reikia?'], ['Žinoma. Prašymas aiškus.', 'Gerai. Tai supratau.'], 'final-service-request', 'Make a request', 'Pasakykite prašymą', 'The learner orders, buys, asks price, pays, or says a health need.', 'Mokinys užsisako, perka, klausia kainos, moka arba pasako sveikatos poreikį.', 'Norėčiau kavos, prašau.', 'planas', ['Norėčiau kavos, prašau.', 'Kiek kainuoja?', 'Mokėsiu kortele.', 'Man skauda galvą.', 'Man reikia vandens.']),
                    $this->capstoneScene('planas', 'Plan', 'Planas', 'Gabija asks for a simple time, place, or yes/no answer.', 'Gabija prašo paprasto laiko, vietos arba taip/ne atsakymo.', ['Kada galime susitikti?', 'Ar galite susitikti rytoj?'], ['Puiku. Planas aiškus.', 'Gerai. Dabar užbaikime pokalbį.'], 'final-plan', 'Give a simple plan', 'Pasakykite paprastą planą', 'The learner accepts, declines, or gives a simple meeting time/place.', 'Mokinys sutinka, atsisako arba pasako paprastą susitikimo laiką ar vietą.', 'Susitinkame rytoj šeštą valandą.', 'pabaiga', ['Taip, galiu.', 'Atsiprašau, negaliu.', 'Susitinkame rytoj.', 'Susitinkame šeštą valandą.', 'Susitinkame prie kavinės.']),
                    $this->capstoneScene('pabaiga', 'Closing', 'Pabaiga', 'Gabija asks the learner to close politely.', 'Gabija prašo mokinio mandagiai užbaigti pokalbį.', ['Kaip užbaigsite pokalbį?', 'Pasakykite mandagų atsisveikinimą.'], ['Labai gerai. A1 kartojimas baigtas.', 'Puiku. Pokalbis baigtas.'], 'final-goodbye', 'Close politely', 'Mandagiai atsisveikinkite', 'The learner thanks and says goodbye politely.', 'Mokinys padėkoja ir mandagiai atsisveikina.', 'Ačiū, viso gero.', null, ['Ačiū.', 'Ačiū, viso gero.', 'Viso gero.', 'Iki.', 'Geros dienos.']),
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
                    'accepted_phrases' => $data['accepted_phrases'],
                    'next' => null,
                    'translations' => [
                        'label' => ['en' => $data['goal_label'], 'lt' => $data['goal_label_lt']],
                        'intent' => ['en' => $data['goal_intent'], 'lt' => $data['goal_intent_lt']],
                    ],
                ]],
            ]],
        ];
    }

    private function capstoneScene(string $slug, string $title, string $titleLt, string $setting, string $settingLt, array $openings, array $replies, string $goalSlug, string $goalLabel, string $goalLabelLt, string $intent, string $intentLt, string $example, ?string $next, array $acceptedPhrases): array
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
                'accepted_phrases' => $acceptedPhrases,
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
            'Trumpai prisistatykite.' => 'Introduce yourself briefly.',
            'Pasakykite, kas jūs esate.' => 'Say who you are.',
            'Malonu susipažinti.' => 'Nice to meet you.',
            'Puiku. Prisistatymas aiškus.' => 'Great. The introduction is clear.',
            'Ko jums reikia?' => 'What do you need?',
            'Pasakykite, ko norite arba ką galite.' => 'Say what you want or what you can do.',
            'Gerai. Poreikis aiškus.' => 'Good. The need is clear.',
            'Suprantu. Ačiū, kad pasakėte.' => 'I understand. Thank you for saying that.',
            'Kaip galiu padėti mieste?' => 'How can I help in town?',
            'Ko ieškote mieste?' => 'What are you looking for in town?',
            'Gerai. Klausimas aiškus.' => 'Good. The question is clear.',
            'Žinoma. Padėsiu rasti vietą.' => 'Of course. I will help find the place.',
            'Kuo galiu padėti?' => 'How can I help?',
            'Ko pageidaujate?' => 'What would you like?',
            'Žinoma. Prašymas aiškus.' => 'Of course. The request is clear.',
            'Gerai. Galiu padėti.' => 'Good. I can help.',
            'Kada ir kur susitinkame?' => 'When and where are we meeting?',
            'Ar galite susitikti?' => 'Can you meet?',
            'Puiku. Planas aiškus.' => 'Great. The plan is clear.',
            'Gerai. Supratau planą.' => 'Good. I understood the plan.',
            'Sveiki. Trumpai prisistatykite.' => 'Hello. Introduce yourself briefly.',
            'Laba diena. Kas jūs esate?' => 'Good afternoon. Who are you?',
            'Malonu susipažinti. Tęskime.' => 'Nice to meet you. Let us continue.',
            'Puiku. Dabar pakalbėkime apie miestą.' => 'Great. Now let us talk about the town.',
            'Kur norite eiti?' => 'Where do you want to go?',
            'Gerai. Padėsiu jums.' => 'Good. I will help you.',
            'Aišku. Vieta ar kelionė suprantama.' => 'Clear. The place or trip is understood.',
            'Gerai. Tai supratau.' => 'Good. I understood that.',
            'Kada galime susitikti?' => 'When can we meet?',
            'Ar galite susitikti rytoj?' => 'Can you meet tomorrow?',
            'Gerai. Dabar užbaikime pokalbį.' => 'Good. Now let us close the conversation.',
            'Kaip užbaigsite pokalbį?' => 'How will you close the conversation?',
            'Pasakykite mandagų atsisveikinimą.' => 'Say a polite goodbye.',
            'Labai gerai. A1 kartojimas baigtas.' => 'Very good. The A1 review is complete.',
            'Puiku. Pokalbis baigtas.' => 'Great. The conversation is complete.',
            default => $line,
        };
    }
}
