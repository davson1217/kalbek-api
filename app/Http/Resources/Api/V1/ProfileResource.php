<?php

namespace App\Http\Resources\Api\V1;

use App\Modules\Subscriptions\SubscriptionManager;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $languageCode = $request->string('language')->trim()->lower()->value() ?: 'lt';
        $languageLevel = $this->user?->languageLevels?->firstWhere('language_code', $languageCode);
        $subscription = $this->user ? app(SubscriptionManager::class)->accessFor($this->user)->toArray() : null;

        return [
            'display_name' => $this->display_name,
            'avatar_character' => $this->whenLoaded('avatarCharacter', fn () => $this->avatarCharacter ? [
                'id' => $this->avatarCharacter->slug,
                'name' => $this->avatarCharacter->name,
                'role' => $this->avatarCharacter->role,
                'image_path' => $this->avatarCharacter->image_path,
            ] : null),
            'xp' => $this->xp,
            'hearts' => $this->hearts,
            'streak' => $this->streak,
            'longest_streak' => $this->longest_streak,
            'last_practice_date' => $this->last_practice_date?->toDateString(),
            'strict_speech_mode' => (bool) $this->user?->strict_speech_mode,
            'strict_speech_mode_explanation' => 'Strict mode makes speech checks more demanding. Kalbek expects your answer to match the task more closely and may ask you to try again for grammar, vocabulary, or relevance issues.',
            'subscription' => $subscription,
            'language_level' => $languageLevel ? [
                'language_code' => $languageLevel->language_code,
                'current_cefr_level' => $languageLevel->current_cefr_level->value,
                'current_cefr_label' => $languageLevel->current_cefr_level->label(),
                'confidence_score' => $languageLevel->confidence_score,
                'grammar_score' => $languageLevel->grammar_score,
                'vocabulary_score' => $languageLevel->vocabulary_score,
                'cohesion_score' => $languageLevel->cohesion_score,
                'task_completion_score' => $languageLevel->task_completion_score,
                'pronunciation_score' => $languageLevel->pronunciation_score,
                'evidence_attempts_count' => $languageLevel->evidence_attempts_count,
                'evidence_window_started_at' => $languageLevel->evidence_window_started_at?->toDateString(),
                'evidence_window_ended_at' => $languageLevel->evidence_window_ended_at?->toDateString(),
                'last_evaluated_at' => $languageLevel->last_evaluated_at?->toISOString(),
                'explanation' => 'Your proficiency level is estimated over time from speaking attempts, using grammar, vocabulary range, cohesion and coherence, task completion, and pronunciation evidence when available.',
            ] : [
                'language_code' => $languageCode,
                'current_cefr_level' => 'pre_a1',
                'current_cefr_label' => 'Pre-A1',
                'confidence_score' => 0,
                'grammar_score' => null,
                'vocabulary_score' => null,
                'cohesion_score' => null,
                'task_completion_score' => null,
                'pronunciation_score' => null,
                'evidence_attempts_count' => 0,
                'evidence_window_started_at' => null,
                'evidence_window_ended_at' => null,
                'last_evaluated_at' => null,
                'explanation' => 'Your proficiency level is estimated over time from speaking attempts, using grammar, vocabulary range, cohesion and coherence, task completion, and pronunciation evidence when available.',
            ],
        ];
    }
}
