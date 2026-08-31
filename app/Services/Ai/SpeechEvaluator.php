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
    ): array {
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
                'communication' => [
                    'intent_match' => 'off_topic',
                    'understood_meaning' => false,
                    'went_off_script' => false,
                    'note' => 'I could not understand the spoken answer yet.',
                    'improvement_focus' => 'pronunciation',
                ],
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
        $contentCefrLevel = $this->validCefrLevel($contentCefrLevel, CefrLevel::A1->value);
        $learnerCefrLevel = $this->validCefrLevel($learnerCefrLevel, CefrLevel::PreA1->value);
        $verdict = $this->verdictArray(SpeakingJudge::make()->prompt(
            "Situation: {$context}\n"
            ."The learner must express: {$intent}\n"
            ."Model phrasing: {$example}\n"
            ."Content CEFR level: {$contentCefrLevel}\n"
            ."Current estimated learner CEFR level: {$learnerCefrLevel}\n"
            ."Judge relative to the content CEFR level. Use the learner level only as background for encouraging feedback, not as a reason to fail a valid answer.\n"
            ."The learner may answer naturally and include extra social detail. Judge whether the current communicative goal was answered, not whether the exact model phrase was repeated.\n"
            .'Evaluation mode: '.$this->evaluationModeInstruction($strict, $contentCefrLevel)."\n"
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
            'communication' => $this->communicationFromVerdict($verdict, (bool) ($verdict['pass'] ?? false)),
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

    /**
     * @return array{intent_match: string, understood_meaning: bool, went_off_script: bool, note: string, improvement_focus: string}
     */
    private function communicationFromVerdict(array $verdict, bool $passed): array
    {
        $intentMatch = (string) ($verdict['intent_match'] ?? ($passed ? 'full' : 'partial'));
        $focus = (string) ($verdict['improvement_focus'] ?? ($passed ? 'none' : 'task'));

        return [
            'intent_match' => in_array($intentMatch, ['full', 'partial', 'off_topic'], true) ? $intentMatch : ($passed ? 'full' : 'partial'),
            'understood_meaning' => (bool) ($verdict['understood_meaning'] ?? $passed),
            'went_off_script' => (bool) ($verdict['went_off_script'] ?? false),
            'note' => $this->speechFirstFeedback((string) ($verdict['communication_note'] ?? ($passed ? 'You answered the goal clearly.' : 'Your answer was related, but did not fully answer the goal.')), ''),
            'improvement_focus' => in_array($focus, ['grammar', 'vocabulary', 'pronunciation', 'coherence', 'task', 'none'], true) ? $focus : ($passed ? 'none' : 'task'),
        ];
    }

    private function evaluationModeInstruction(bool $strict, string $contentCefrLevel): string
    {
        $levelGuidance = match ($contentCefrLevel) {
            'pre_a1', 'a1' => 'For Pre-A1/A1 content, prioritize clear communicative success, simple correct phrases, and basic vocabulary. Do not expect complex sentences.',
            'a2' => 'For A2 content, expect simple connected phrases, basic tense control, and enough detail for the task.',
            'b1' => 'For B1 content, expect connected explanation, relevant detail, and mostly controlled everyday grammar.',
            'b2' => 'For B2 content, expect nuance, register control, cohesive reasoning, and a broader vocabulary range.',
            'c1', 'c2' => 'For C-level content, expect precise expression, strong cohesion, idiomatic control, and register awareness.',
            default => 'Judge against the provided content level and role-play goal.',
        };

        $mode = $strict
            ? 'STRICT. Be less forgiving within the same CEFR level: fail loose matches, mostly English answers, omitted required meaning, or grammar/vocabulary problems that make the role-play response unnatural. Require task_completion of at least 75 for pass.'
            : 'NORMAL. Pass understandable Lithuanian that satisfies the task at the content level, even with minor spoken grammar or phrasing slips.';

        return $mode.' '.$levelGuidance;
    }

    private function validCefrLevel(string $level, string $fallback): string
    {
        return in_array($level, array_column(CefrLevel::cases(), 'value'), true) ? $level : $fallback;
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
