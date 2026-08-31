<?php

namespace Database\Factories;

use App\Models\Language;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Language>
 */
class LanguageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->lexify('??'),
            'name' => fake()->languageCode(),
            'native_name' => fake()->languageCode(),
            'support_language_code' => 'en',
            'support_language_name' => 'English',
            'status' => 'active',
            'default_voice' => null,
            'sort_order' => fake()->numberBetween(1, 50),
        ];
    }
}
