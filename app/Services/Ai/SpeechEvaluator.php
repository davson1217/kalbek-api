<?php

namespace App\Services\Ai;

use App\Ai\Agents\SpeakingJudge;
use Illuminate\Http\UploadedFile;
use Laravel\Ai\Transcription;

class SpeechEvaluator
{
    /**
     * @return array{transcript: string, pass: bool, feedback: string, corrected: string, suggestion: string}
     */
    public function evaluate(UploadedFile $audio, string $intent, string $example = '', string $context = ''): array
    {
        $transcript = trim((string) Transcription::fromUpload($audio)
            ->language('lt')
            ->timeout(45)
            ->generate());

        if ($transcript === '') {
            return [
                'transcript' => '',
                'pass' => false,
                'feedback' => 'I could not hear anything, try speaking a little louder.',
                'corrected' => '',
                'suggestion' => $example,
            ];
        }

        $verdict = SpeakingJudge::make()->prompt(
            "Situation: {$context}\n"
            ."The learner must express: {$intent}\n"
            ."Model phrasing: {$example}\n"
            ."The learner said, transcribed: \"{$transcript}\"",
            timeout: 45,
        );

        return [
            'transcript' => $transcript,
            'pass' => (bool) ($verdict['pass'] ?? false),
            'feedback' => (string) ($verdict['feedback'] ?? 'Try that again.'),
            'corrected' => (string) ($verdict['corrected'] ?? ''),
            'suggestion' => $example,
        ];
    }
}
