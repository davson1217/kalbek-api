<?php

namespace App\Contracts;

use Illuminate\Http\UploadedFile;

interface SpeechEvaluatorContract
{
    /**
     * @return array{transcript: string, normalized_transcript: string, normalization_confidence: string, normalization_note: string, pass: bool, can_continue: bool, should_retry: bool, retry_reason: string, feedback: string, corrected: string, suggested_response: string, suggestion: string, communication: array{intent_match: string, understood_meaning: bool, went_off_script: bool, note: string, improvement_focus: string}, scores: array{grammar: int, vocabulary: int, cohesion: int, task_completion: int, pronunciation: int|null}, overall_score: int, attempt_cefr_level: string|null, evaluation_provider: string|null, evaluation_model: string|null}
     */
    public function evaluate(
        UploadedFile $audio,
        string $intent,
        string $example = '',
        string $context = '',
        bool $strict = false,
        string $contentCefrLevel = 'a1',
        string $learnerCefrLevel = 'pre_a1',
        string $targetLanguageCode = 'lt',
        string $targetLanguageName = 'Lithuanian',
        string $feedbackLanguageCode = 'en',
        string $feedbackLanguageName = 'English',
    ): array;
}
