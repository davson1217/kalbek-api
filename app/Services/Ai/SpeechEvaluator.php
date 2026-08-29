<?php

namespace App\Services\Ai;

use App\Ai\Agents\SpeakingJudge;
use App\CefrLevel;
use App\Contracts\SpeechEvaluatorContract;
use Illuminate\Http\UploadedFile;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Laravel\Ai\Transcription;

class SpeechEvaluator implements SpeechEvaluatorContract
{
    /**
     * @return array{transcript: string, pass: bool, feedback: string, corrected: string, suggestion: string, scores: array{grammar: int, vocabulary: int, cohesion: int, task_completion: int, pronunciation: int|null}, overall_score: int, attempt_cefr_level: string|null, evaluation_provider: string|null, evaluation_model: string|null}
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
                'scores' => [
                    'grammar' => 0,
                    'vocabulary' => 0,
                    'cohesion' => 0,
                    'task_completion' => 0,
                    'pronunciation' => null,
                ],
                'overall_score' => 0,
                'attempt_cefr_level' => CefrLevel::PreA1->value,
                'evaluation_provider' => config('ai.default'),
                'evaluation_model' => null,
            ];
        }

        $verdict = $this->verdictArray(SpeakingJudge::make()->prompt(
            "Situation: {$context}\n"
            ."The learner must express: {$intent}\n"
            ."Model phrasing: {$example}\n"
            ."The learner said, transcribed: \"{$transcript}\"",
            timeout: 45,
        ));

        $scores = $this->scoresFromVerdict($verdict);
        $overallScore = $this->overallScore($scores);

        return [
            'transcript' => $transcript,
            'pass' => (bool) ($verdict['pass'] ?? false),
            'feedback' => (string) ($verdict['feedback'] ?? 'Try that again.'),
            'corrected' => (string) ($verdict['corrected'] ?? ''),
            'suggestion' => $example,
            'scores' => $scores,
            'overall_score' => $overallScore,
            'attempt_cefr_level' => (string) ($verdict['attempt_cefr_level'] ?? CefrLevel::estimateFromScore($overallScore)->value),
            'evaluation_provider' => config('ai.default'),
            'evaluation_model' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $verdict
     * @return array{grammar: int, vocabulary: int, cohesion: int, task_completion: int, pronunciation: int|null}
     */
    private function verdictArray(StructuredAgentResponse|array $verdict): array
    {
        return $verdict instanceof StructuredAgentResponse ? $verdict->toArray() : $verdict;
    }

    private function scoresFromVerdict(array $verdict): array
    {
        $scores = is_array($verdict['scores'] ?? null) ? $verdict['scores'] : [];

        return [
            'grammar' => $this->score($scores['grammar'] ?? null),
            'vocabulary' => $this->score($scores['vocabulary'] ?? null),
            'cohesion' => $this->score($scores['cohesion'] ?? null),
            'task_completion' => $this->score($scores['task_completion'] ?? null),
            'pronunciation' => isset($scores['pronunciation']) ? $this->score($scores['pronunciation']) : null,
        ];
    }

    private function score(mixed $value): int
    {
        return max(0, min(100, (int) $value));
    }

    /**
     * @param  array{grammar: int, vocabulary: int, cohesion: int, task_completion: int, pronunciation: int|null}  $scores
     */
    private function overallScore(array $scores): int
    {
        $values = array_filter($scores, fn (?int $score): bool => $score !== null);

        return (int) round(array_sum($values) / count($values));
    }
}
