<?php

namespace Database\Factories;

use App\Models\Scene;
use App\Models\SceneProp;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SceneProp>
 */
class ScenePropFactory extends Factory
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
            'type' => 'menu_item',
            'target_text' => fake()->words(2, true),
            'support_translation' => fake()->words(2, true),
            'price' => fake()->randomFloat(2, 2, 20).' €',
            'metadata' => null,
            'sort_order' => fake()->numberBetween(1, 50),
        ];
    }
}
