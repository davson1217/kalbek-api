<?php

namespace Tests\Unit\Services\Progress;

use App\Models\Profile;
use App\Services\Progress\UpdateStreak;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UpdateStreakTest extends TestCase
{
    /**
     * @return array<string, array{last_practice_date: string|null, current_streak: int, expected_streak: int}>
     */
    public static function streakCases(): array
    {
        return [
            'first practice day' => [
                null,
                0,
                1,
            ],
            'same day keeps streak' => [
                '2026-08-29',
                4,
                4,
            ],
            'consecutive day increments streak' => [
                '2026-08-28',
                4,
                5,
            ],
            'missed day resets streak' => [
                '2026-08-27',
                4,
                1,
            ],
        ];
    }

    #[DataProvider('streakCases')]
    public function test_it_calculates_the_next_streak(
        ?string $lastPracticeDate,
        int $currentStreak,
        int $expectedStreak,
    ): void {
        $profile = new Profile([
            'streak' => $currentStreak,
            'last_practice_date' => $lastPracticeDate,
        ]);

        $streak = app(UpdateStreak::class)->handle($profile, CarbonImmutable::parse('2026-08-29'));

        $this->assertSame($expectedStreak, $streak);
    }
}
