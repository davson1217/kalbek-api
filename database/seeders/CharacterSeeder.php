<?php

namespace Database\Seeders;

use App\ContentStatus;
use App\Models\Character;
use App\Models\Language;
use Illuminate\Database\Seeder;

class CharacterSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(LanguageSeeder::class);

        $lithuanian = Language::query()->where('code', 'lt')->firstOrFail();
        $english = Language::query()->where('code', 'en')->firstOrFail();

        $characters = [
            [
                'language_id' => $lithuanian->id,
                'slug' => 'gabija',
                'name' => 'Gabija',
                'role' => 'your speaking coach',
                'image_path' => 'characters/gabija.png',
                'intro' => "Sveiki! Choose a scene and let's speak Lithuanian out loud.",
                'tts_voice' => 'default-female',
                'speaking_style' => 'Warm, encouraging, clear, and slightly slow, like a patient Lithuanian speaking coach.',
                'praise_lines' => ['Puiku!', 'Šaunuolis!', 'Labai gerai!'],
                'encouragement_lines' => ['Bandyk dar kartą.', 'Beveik pavyko!', 'Nieko tokio — pakartok.'],
                'sort_order' => 10,
                'status' => ContentStatus::Published,
            ],
            [
                'language_id' => $lithuanian->id,
                'slug' => 'rasa',
                'name' => 'Rasa',
                'role' => 'the waitress',
                'image_path' => 'characters/rasa.png',
                'intro' => 'Laba diena! Sveiki atvykę.',
                'tts_voice' => 'default-female',
                'speaking_style' => 'Friendly, practical, and natural, like a patient service worker speaking clearly to a beginner.',
                'praise_lines' => ['Puiku!', 'Supratau, ačiū!', 'Labai gerai pasakyta.'],
                'encouragement_lines' => [
                    'Atsiprašau, nesupratau. Pakartokite, prašau.',
                    'Dar kartą, prašau.',
                    'Beveik!',
                ],
                'sort_order' => 20,
                'status' => ContentStatus::Published,
            ],
            [
                'language_id' => $english->id,
                'slug' => 'emily',
                'name' => 'Emily',
                'role' => 'the shop assistant',
                'image_path' => 'characters/emily.png',
                'intro' => "Hello! Choose a scene and let's speak English out loud.",
                'tts_voice' => 'default-female',
                'speaking_style' => 'Friendly, clear, and slightly slow, like a helpful shop assistant speaking to a beginner.',
                'praise_lines' => ['Great!', 'Very good!', 'Nicely said.'],
                'encouragement_lines' => [
                    'Try once more.',
                    'Almost there.',
                    'That was close. Please repeat it.',
                ],
                'sort_order' => 30,
                'status' => ContentStatus::Published,
            ],
        ];

        foreach ($characters as $character) {
            Character::query()->updateOrCreate(
                ['slug' => $character['slug']],
                $character,
            );
        }
    }
}
