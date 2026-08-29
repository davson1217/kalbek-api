<?php

namespace App\Services\Progress;

use App\Models\Profile;
use Carbon\CarbonImmutable;

class UpdateStreak
{
    public function handle(Profile $profile, CarbonImmutable $practiceDate): int
    {
        $today = $practiceDate->toDateString();
        $lastPracticeDate = $profile->last_practice_date?->toDateString();

        if ($lastPracticeDate === null) {
            return 1;
        }

        if ($lastPracticeDate === $today) {
            return max($profile->streak, 1);
        }

        if (CarbonImmutable::parse($lastPracticeDate)->addDay()->toDateString() === $today) {
            return $profile->streak + 1;
        }

        return 1;
    }
}
