<?php

namespace Database\Factories;

use App\AiPromptPurpose;
use App\Models\AiPrompt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiPrompt>
 */
class AiPromptFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'purpose' => AiPromptPurpose::SpeakingGrading,
            'version' => fake()->unique()->numberBetween(1, 10_000),
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'system_message' => fake()->paragraph(),
            'parameters' => null,
            'active' => true,
        ];
    }
}
