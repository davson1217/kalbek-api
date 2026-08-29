<?php

namespace Database\Factories;

use App\ContentStatus;
use App\Models\Character;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Character>
 */
class CharacterFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(2),
            'name' => fake()->firstName(),
            'role' => fake()->words(3, true),
            'image_path' => null,
            'intro' => fake()->sentence(),
            'praise_lines' => [fake()->sentence()],
            'encouragement_lines' => [fake()->sentence()],
            'sort_order' => fake()->numberBetween(1, 50),
            'status' => ContentStatus::Published,
        ];
    }
}
