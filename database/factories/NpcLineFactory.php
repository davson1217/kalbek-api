<?php

namespace Database\Factories;

use App\Models\NpcLine;
use App\Models\Scene;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NpcLine>
 */
class NpcLineFactory extends Factory
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
            'lt' => fake()->sentence(),
            'en' => fake()->sentence(),
            'sort_order' => fake()->numberBetween(1, 50),
        ];
    }
}
