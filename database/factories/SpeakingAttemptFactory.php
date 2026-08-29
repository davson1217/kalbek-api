<?php

namespace Database\Factories;

use App\Models\Goal;
use App\Models\Scenario;
use App\Models\Scene;
use App\Models\SpeakingAttempt;
use App\Models\User;
use App\SpeakingAttemptStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SpeakingAttempt>
 */
class SpeakingAttemptFactory extends Factory
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
            'scene_id' => Scene::factory(),
            'goal_id' => Goal::factory(),
            'status' => SpeakingAttemptStatus::Pending,
            'audio_path' => null,
            'transcript' => null,
            'passed' => null,
            'score' => null,
            'feedback' => null,
            'corrected_text' => null,
            'metadata' => null,
            'graded_at' => null,
        ];
    }
}
