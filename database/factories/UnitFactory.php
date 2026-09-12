<?php

namespace Database\Factories;

use App\ContentStatus;
use App\Models\Language;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Unit>
 */
class UnitFactory extends Factory
{
    protected $model = Unit::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'language_id' => fn (): int => Language::query()->where('code', 'lt')->value('id')
                ?? Language::factory()->create([
                    'code' => 'lt',
                    'name' => 'Lithuanian',
                    'native_name' => 'Lietuvių',
                    'support_language_code' => 'en',
                    'support_language_name' => 'English',
                    'sort_order' => 10,
                ])->id,
            'slug' => fake()->unique()->slug(2),
            'title' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'cefr_level' => 'a1',
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
