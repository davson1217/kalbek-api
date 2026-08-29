<?php

namespace App\Services\Progress;

use App\Models\LessonProgress;
use App\Models\Profile;
use App\Models\Scenario;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class CompleteLesson
{
    public function __construct(private UpdateStreak $updateStreak) {}

    public function handle(User $user, Scenario $scenario, int $score, int $xp): LessonProgress
    {
        return DB::transaction(function () use ($user, $scenario, $score, $xp): LessonProgress {
            $progress = LessonProgress::query()->firstOrNew([
                'user_id' => $user->id,
                'scenario_id' => $scenario->id,
            ]);

            $progress->fill([
                'completed' => true,
                'best_score' => max($score, $progress->best_score ?? 0),
                'xp_earned' => ($progress->xp_earned ?? 0) + $xp,
                'attempts' => ($progress->attempts ?? 0) + 1,
                'completed_at' => now(),
            ]);
            $progress->save();

            $profile = $user->profile()->lockForUpdate()->first();

            if (! $profile) {
                $profile = $user->profile()->create([
                    'display_name' => $user->name,
                ]);
            }

            /** @var Profile $profile */
            $streak = $this->updateStreak->handle($profile, CarbonImmutable::now());

            $profile->update([
                'xp' => $profile->xp + $xp,
                'streak' => $streak,
                'longest_streak' => max($profile->longest_streak, $streak),
                'last_practice_date' => now()->toDateString(),
            ]);

            return $progress->load('scenario');
        });
    }
}
