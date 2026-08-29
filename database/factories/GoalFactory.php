<?php

namespace Database\Factories;

use App\Models\Goal;
use App\Models\Scene;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Goal>
 */
class GoalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'scene_id' => Scene::factory(),
            'next_scene_id' => null,
            'slug' => fake()->unique()->slug(2),
            'label' => fake()->sentence(4),
            'intent' => fake()->sentence(),
            'example' => fake()->sentence(),
            'sort_order' => fake()->numberBetween(1, 50),
        ];
    }
}
