<?php

namespace App\Services\Ai;

use App\Contracts\SpeechEvaluatorContract;
use Illuminate\Http\UploadedFile;

class FakeSpeechEvaluator implements SpeechEvaluatorContract
{
    /**
     * @return array{transcript: string, pass: bool, feedback: string, corrected: string, suggestion: string, communication: array{intent_match: string, understood_meaning: bool, went_off_script: bool, note: string, improvement_focus: string}, scores: array{grammar: int, vocabulary: int, cohesion: int, task_completion: int, pronunciation: int|null}, overall_score: int, attempt_cefr_level: string|null, evaluation_provider: string|null, evaluation_model: string|null}
     */
    public function evaluate(UploadedFile $audio, string $intent, string $example = '', string $context = '', bool $strict = false): array
    {
        $transcript = (string) config('services.kalbek.fake_speech_transcript', 'Ar turite maisto?');
        $pass = (bool) config('services.kalbek.fake_speech_pass', true);

        return [
            'transcript' => $transcript,
            'pass' => $pass,
            'feedback' => (string) config('services.kalbek.fake_speech_feedback', 'Dev AI mode: speech accepted without calling an AI provider.'),
            'corrected' => $example !== '' ? $example : $transcript,
            'suggestion' => $example,
            'communication' => [
                'intent_match' => $pass ? 'full' : 'partial',
                'understood_meaning' => $pass,
                'went_off_script' => false,
                'note' => $pass ? 'The spoken answer satisfies the current goal.' : 'The spoken answer needs another try.',
                'improvement_focus' => $pass ? 'none' : 'task',
            ],
            'scores' => [
                'grammar' => 78,
                'vocabulary' => 74,
                'cohesion' => 72,
                'task_completion' => $pass ? 90 : 35,
                'pronunciation' => null,
            ],
            'overall_score' => $pass ? 79 : 42,
            'attempt_cefr_level' => 'a1',
            'evaluation_provider' => 'fake',
            'evaluation_model' => 'fake-speech-evaluator',
        ];
    }
}
