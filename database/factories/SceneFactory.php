<?php

namespace Database\Factories;

use App\Models\Scenario;
use App\Models\Scene;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Scene>
 */
class SceneFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'scenario_id' => Scenario::factory(),
            'slug' => fake()->unique()->slug(2),
            'setting' => fake()->sentence(),
            'sort_order' => fake()->numberBetween(1, 50),
        ];
    }
}
