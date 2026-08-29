<?php

namespace Database\Factories;

use App\ContentStatus;
use App\Models\Character;
use App\Models\Scenario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Scenario>
 */
class ScenarioFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'character_id' => Character::factory(),
            'slug' => fake()->unique()->slug(2),
            'title' => fake()->words(2, true),
            'subtitle' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'emoji' => '🎯',
            'tone' => 'primary',
            'start_scene_slug' => null,
            'status' => ContentStatus::Published,
            'sort_order' => fake()->numberBetween(1, 50),
            'published_at' => now(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (): array => [
            'status' => ContentStatus::Draft,
            'published_at' => null,
        ]);
    }
}
