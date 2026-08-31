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
    public function evaluate(UploadedFile $audio, string $intent, string $example = '', string $context = '', bool $strict = false): array
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

        $normalizedTranscript = $this->normalizeTranscriptForJudging($transcript);
        $verdict = $this->verdictArray(SpeakingJudge::make()->prompt(
            "Situation: {$context}\n"
            ."The learner must express: {$intent}\n"
            ."Model phrasing: {$example}\n"
            ."The learner may answer naturally and include extra social detail. Judge whether the current communicative goal was answered, not whether the exact model phrase was repeated.\n"
            .'Evaluation mode: '.($strict ? 'STRICT. Fail answers that only loosely match the task, use mostly English, omit the required meaning, or contain grammar/vocabulary problems that make the role-play response unnatural. Require task_completion of at least 75 for pass.' : 'NORMAL. Be encouraging for beginners and pass understandable Lithuanian that satisfies the task, even with small grammar or case slips.')."\n"
            ."Raw speech transcript: \"{$transcript}\"\n"
            ."Normalized transcript for judging: \"{$normalizedTranscript}\"\n"
            .'Judge the spoken answer, not the transcript formatting.',
            timeout: 45,
        ));

        $scores = $this->scoresFromVerdict($verdict);
        $overallScore = $this->overallScore($scores);

        return [
            'transcript' => $transcript,
            'pass' => (bool) ($verdict['pass'] ?? false),
            'feedback' => $this->speechFirstFeedback((string) ($verdict['feedback'] ?? 'Try that again.'), $example),
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

    private function normalizeTranscriptForJudging(string $transcript): string
    {
        return str($transcript)
            ->replaceMatches('/\blabadiena\b/iu', 'Laba diena')
            ->replaceMatches('/\blabasdiena\b/iu', 'Labas diena')
            ->toString();
    }

    private function speechFirstFeedback(string $feedback, string $example): string
    {
        $textOnlyTerms = [
            'capital',
            'capitalization',
            'capitalise',
            'capitalize',
            'case',
            'casing',
            'punctuation',
            'spell',
            'spelling',
            'typed',
            'wrote',
            'written',
        ];

        if (! str($feedback)->lower()->contains($textOnlyTerms)) {
            return $feedback;
        }

        return $example !== ''
            ? "That was understandable. A more natural spoken version is: {$example}"
            : 'That was understandable. Try saying the phrase a little more clearly and naturally.';
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
