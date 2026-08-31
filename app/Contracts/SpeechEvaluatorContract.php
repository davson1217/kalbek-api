<?php

namespace App\Contracts;

use Illuminate\Http\UploadedFile;

interface SpeechEvaluatorContract
{
    /**
     * @return array{transcript: string, pass: bool, feedback: string, corrected: string, suggestion: string, communication: array{intent_match: string, understood_meaning: bool, went_off_script: bool, note: string, improvement_focus: string}, scores: array{grammar: int, vocabulary: int, cohesion: int, task_completion: int, pronunciation: int|null}, overall_score: int, attempt_cefr_level: string|null, evaluation_provider: string|null, evaluation_model: string|null}
     */
    public function evaluate(
        UploadedFile $audio,
        string $intent,
        string $example = '',
        string $context = '',
        bool $strict = false,
        string $contentCefrLevel = 'a1',
        string $learnerCefrLevel = 'pre_a1',
    ): array;
}
