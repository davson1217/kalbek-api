<?php

namespace App\Services\Progress;

use App\CefrLevel;
use App\Models\CefrLevelHistory;
use App\Models\Language;
use App\Models\LearnerLanguageLevel;
use App\Models\SpeakingAttempt;
use App\Models\User;

class RecalculateLearnerCefrLevel
{
    public const MINIMUM_EVIDENCE_ATTEMPTS = 10;

    public const EVIDENCE_WINDOW_DAYS = 60;

    public function recalculateUsersWithNewEvidence(): int
    {
        $count = 0;

        User::query()
            ->whereHas('speakingAttempts', fn ($query) => $query->whereNotNull('overall_score'))
            ->with('languageLevels')
            ->chunkById(100, function ($users) use (&$count): void {
                foreach ($users as $user) {
                    foreach ($this->languageCodesWithEvidence($user) as $languageCode) {
                        if ($this->hasNewEvidence($user, $languageCode)) {
                            $this->recalculate($user, $languageCode);
                            $count++;
                        }
                    }
                }
            });

        return $count;
    }

    public function recalculate(User $user, string $languageCode = 'lt'): LearnerLanguageLevel
    {
        $windowStart = now()->subDays(self::EVIDENCE_WINDOW_DAYS);
        $attempts = $user->speakingAttempts()
            ->whereHas('scenario.language', fn ($query) => $query->where('code', $languageCode))
            ->whereNotNull('overall_score')
            ->where('evaluated_at', '>=', $windowStart)
            ->orderBy('evaluated_at')
            ->get();

        $level = LearnerLanguageLevel::query()->firstOrCreate(
            ['user_id' => $user->id, 'language_code' => $languageCode],
            ['current_cefr_level' => CefrLevel::PreA1, 'confidence_score' => 0],
        );

        if ($attempts->isEmpty()) {
            $level->update([
                'confidence_score' => 0,
                'evidence_attempts_count' => 0,
                'evidence_window_started_at' => null,
                'evidence_window_ended_at' => null,
                'last_evaluated_at' => now(),
            ]);

            return $level->refresh();
        }

        $averages = [
            'grammar_score' => $this->average($attempts, 'grammar_score'),
            'vocabulary_score' => $this->average($attempts, 'vocabulary_score'),
            'cohesion_score' => $this->average($attempts, 'cohesion_score'),
            'task_completion_score' => $this->average($attempts, 'task_completion_score'),
            'pronunciation_score' => $this->average($attempts, 'pronunciation_score'),
        ];
        $overall = $this->average($attempts, 'overall_score') ?? 0;
        $confidence = min(100, $attempts->count() * 10);
        $previous = $level->current_cefr_level ?? CefrLevel::PreA1;
        $next = $this->nextLevel($previous, $attempts->count(), $confidence, $overall, $averages['task_completion_score'] ?? 0);

        $level->update([
            'current_cefr_level' => $next,
            'confidence_score' => $confidence,
            ...$averages,
            'evidence_attempts_count' => $attempts->count(),
            'evidence_window_started_at' => $attempts->first()->evaluated_at,
            'evidence_window_ended_at' => $attempts->last()->evaluated_at,
            'last_evaluated_at' => now(),
        ]);

        if ($next !== $previous) {
            CefrLevelHistory::query()->create([
                'user_id' => $user->id,
                'learner_language_level_id' => $level->id,
                'language_code' => $languageCode,
                'previous_cefr_level' => $previous,
                'new_cefr_level' => $next,
                'confidence_score' => $confidence,
                'evidence_attempts_count' => $attempts->count(),
                'reason' => "Recent {$attempts->count()} attempts averaged {$overall}/100 across the CEFR evidence window.",
                'evaluated_at' => now(),
            ]);
        }

        return $level->refresh();
    }

    private function hasNewEvidence(User $user, string $languageCode): bool
    {
        $level = $user->languageLevels->firstWhere('language_code', $languageCode);
        $lastEvaluatedAt = $level?->last_evaluated_at;

        return $user->speakingAttempts()
            ->whereHas('scenario.language', fn ($query) => $query->where('code', $languageCode))
            ->whereNotNull('overall_score')
            ->when($lastEvaluatedAt, fn ($query) => $query->where('evaluated_at', '>', $lastEvaluatedAt))
            ->exists();
    }

    /** @return array<int, string> */
    private function languageCodesWithEvidence(User $user): array
    {
        $codes = Language::query()
            ->whereHas('scenarios.speakingAttempts', fn ($query) => $query
                ->where('user_id', $user->id)
                ->whereNotNull('overall_score'))
            ->pluck('code')
            ->all();

        return $codes === [] ? ['lt'] : $codes;
    }

    private function nextLevel(CefrLevel $current, int $attempts, int $confidence, int $overall, int $taskCompletion): CefrLevel
    {
        if ($attempts < self::MINIMUM_EVIDENCE_ATTEMPTS || $confidence < 60) {
            return $current;
        }

        if ($overall >= 75 && $taskCompletion >= 75) {
            return $current->next();
        }

        if ($overall < 45) {
            return $current->previous();
        }

        return $current;
    }

    private function average($attempts, string $column): ?int
    {
        $values = $attempts
            ->map(fn (SpeakingAttempt $attempt) => $attempt->{$column})
            ->filter(fn ($value): bool => $value !== null);

        if ($values->isEmpty()) {
            return null;
        }

        return (int) round($values->average());
    }
}
