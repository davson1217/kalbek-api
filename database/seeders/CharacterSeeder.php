<?php

namespace Database\Seeders;

use App\ContentStatus;
use App\Models\Character;
use Illuminate\Database\Seeder;

class CharacterSeeder extends Seeder
{
    public function run(): void
    {
        $characters = [
            [
                'slug' => 'gabija',
                'name' => 'Gabija',
                'role' => 'your speaking coach',
                'image_path' => 'characters/gabija.png',
                'intro' => "Sveiki! Choose a scene and let's speak Lithuanian out loud.",
                'praise_lines' => ['Puiku!', 'Šaunuolis!', 'Labai gerai!'],
                'encouragement_lines' => ['Bandyk dar kartą.', 'Beveik pavyko!', 'Nieko tokio — pakartok.'],
                'sort_order' => 10,
                'status' => ContentStatus::Published,
            ],
            [
                'slug' => 'rasa',
                'name' => 'Rasa',
                'role' => 'the waitress',
                'image_path' => 'characters/rasa.png',
                'intro' => 'Laba diena! Sveiki atvykę.',
                'praise_lines' => ['Puiku!', 'Supratau, ačiū!', 'Labai gerai pasakyta.'],
                'encouragement_lines' => [
                    'Atsiprašau, nesupratau. Pakartokite, prašau.',
                    'Dar kartą, prašau.',
                    'Beveik!',
                ],
                'sort_order' => 20,
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
