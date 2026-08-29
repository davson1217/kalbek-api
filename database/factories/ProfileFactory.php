<?php

namespace Database\Factories;

use App\Models\Character;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Profile>
 */
class ProfileFactory extends Factory
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
            'avatar_character_id' => Character::factory(),
            'display_name' => fake()->firstName(),
            'xp' => 0,
            'hearts' => 5,
            'streak' => 0,
            'longest_streak' => 0,
            'last_practice_date' => null,
        ];
    }
}
