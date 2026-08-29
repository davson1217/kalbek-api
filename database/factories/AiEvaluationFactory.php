<?php

namespace Database\Factories;

use App\Models\AiEvaluation;
use App\Models\AiPrompt;
use App\Models\SpeakingAttempt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiEvaluation>
 */
class AiEvaluationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'speaking_attempt_id' => SpeakingAttempt::factory(),
            'ai_prompt_id' => AiPrompt::factory(),
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'input' => ['message' => fake()->sentence()],
            'output' => ['pass' => true],
            'prompt_tokens' => fake()->numberBetween(50, 200),
            'completion_tokens' => fake()->numberBetween(10, 100),
            'total_tokens' => fake()->numberBetween(60, 300),
            'latency_ms' => fake()->numberBetween(200, 5_000),
            'status' => 'succeeded',
            'error_message' => null,
        ];
    }
}
