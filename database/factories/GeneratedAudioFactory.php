<?php

namespace Database\Factories;

use App\Models\GeneratedAudio;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<GeneratedAudio>
 */
class GeneratedAudioFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $text = fake()->sentence();

        return [
            'cache_key' => hash('sha256', $text.Str::random()),
            'text' => $text,
            'voice' => 'alloy',
            'provider' => 'openai',
            'model' => 'gpt-4o-mini-tts',
            'disk' => 'local',
            'path' => 'generated-audio/'.Str::uuid().'.mp3',
            'mime_type' => 'audio/mpeg',
            'bytes' => fake()->numberBetween(2_000, 500_000),
            'last_used_at' => now(),
        ];
    }
}
