<?php

namespace Database\Factories;

use App\Models\LessonProgress;
use App\Models\Scenario;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LessonProgress>
 */
class LessonProgressFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'scenario_id' => Scenario::factory(),
            'completed' => false,
            'best_score' => 0,
            'xp_earned' => 0,
            'attempts' => 0,
            'completed_at' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'completed' => true,
            'best_score' => fake()->numberBetween(70, 100),
            'xp_earned' => fake()->numberBetween(60, 180),
            'attempts' => fake()->numberBetween(1, 5),
            'completed_at' => now(),
        ]);
    }
}
