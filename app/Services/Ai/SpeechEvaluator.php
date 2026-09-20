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
        array $acceptedPhrases = [],
    ): array {
        $transcriptionProvider = config('ai.default_for_transcription');
        $transcriptionModel = config('services.kalbek.transcription_model');

        $transcript = trim((string) Transcription::fromUpload($audio)
            ->language($targetLanguageCode)
            ->timeout(45)
            ->generate(provider: $transcriptionProvider, model: $transcriptionModel));

        if ($transcript === '') {
            return [
                'transcript' => '',
                'normalized_transcript' => '',
                'normalization_confidence' => 'low',
                'normalization_note' => $this->localizedMessage('no_speech_detected', $feedbackLanguageCode),
                'pass' => false,
                'can_continue' => false,
                'should_retry' => true,
                'retry_reason' => $this->localizedMessage('no_speech_detected', $feedbackLanguageCode),
                'feedback' => $this->localizedMessage('speak_louder', $feedbackLanguageCode),
                'corrected' => '',
                'suggested_response' => $example,
                'suggestion' => $example,
                'communication' => [
                    'intent_match' => 'off_topic',
                    'understood_meaning' => false,
                    'went_off_script' => false,
                    'note' => $this->localizedMessage('not_understood', $feedbackLanguageCode),
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

        /**
         * An interpretation layer is drafted in to consolidate a transcription since Lithuanian transcription
         * with the available models may be somewhat inaccurate.
         *
         * It uses the accepted phrases array to calculate a Levenshtein distance. The closest string in the array is used.
        */
        $interpretation = (new SpeechInterpretation)->interpret(
            $transcript,
            $example,
            $acceptedPhrases,
            $feedbackLanguageCode,
        );

        $normalizedTranscript = $interpretation['transcript'];
        $contentCefrLevel = $this->validCefrLevel($contentCefrLevel, CefrLevel::A1->value);
        $learnerCefrLevel = $this->validCefrLevel($learnerCefrLevel, CefrLevel::PreA1->value);
        $acceptedPhraseText = collect($acceptedPhrases)->filter()->values()->implode(' | ');
        $verdict = $this->verdictArray(SpeakingJudge::make()->prompt(
            "Situation: {$context}\n"
            ."Target language: {$targetLanguageName} ({$targetLanguageCode})\n"
            ."Feedback language: {$feedbackLanguageName} ({$feedbackLanguageCode})\n"
            ."The learner must express: {$intent}\n"
            ."Reference learner phrasing: {$example}\n"
            ."Accepted phrases for this exact goal: {$acceptedPhraseText}\n"
            ."Content CEFR level: {$contentCefrLevel}\n"
            ."Current estimated learner CEFR level: {$learnerCefrLevel}\n"
            ."Judge relative to the content CEFR level. Use the learner level only as background for encouraging feedback, not as a reason to fail a valid answer.\n"
            ."The learner may answer naturally and include extra social detail. Judge whether the current communicative goal was answered, not whether the exact model phrase was repeated.\n"
            .'Evaluation mode: '.$this->evaluationModeInstruction($strict, $contentCefrLevel, $targetLanguageName)."\n"
            ."Raw speech transcript: \"{$transcript}\"\n"
            ."Interpreted transcript for judging: \"{$normalizedTranscript}\"\n"
            ."Interpretation confidence: {$interpretation['confidence']}\n"
            ."Interpretation source: {$interpretation['source']}\n"
            ."Judge the spoken answer, not the transcript formatting.\n"
            ."When interpretation confidence is high or medium, reason from the interpreted transcript, not raw STT artifacts. When confidence is low, request a retry for clearer audio instead of criticizing grammar.\n"
            ."Write feedback, retry_reason, normalization_note, and communication_note in {$feedbackLanguageName}. Keep normalized_transcript and suggested_response in {$targetLanguageName}.",
            timeout: 45,
        ));

        $scores = $this->scoresFromVerdict($verdict);
        $overallScore = $this->overallScore($scores);
        $pass = (bool) ($verdict['pass'] ?? false);
        $normalizationConfidence = $this->lowerConfidence(
            $this->normalizationConfidence($verdict['normalization_confidence'] ?? null),
            $interpretation['confidence'],
        );
        $normalizedTranscript = trim((string) ($verdict['normalized_transcript'] ?? $normalizedTranscript));
        $normalizationNote = $this->cleanTutorFeedback(
            $this->speechFirstFeedback((string) ($verdict['normalization_note'] ?? $interpretation['note']), $example, $feedbackLanguageCode),
            $pass,
            $feedbackLanguageCode,
        );
        $strictAcceptedPhraseMiss = $strict && $acceptedPhrases !== [] && $interpretation['source'] !== 'accepted_phrase';
        $shouldRetry = (bool) ($verdict['should_retry'] ?? (! $pass)) || $normalizationConfidence === 'low' || $strictAcceptedPhraseMiss;
        $retryReason = $shouldRetry
            ? $this->cleanTutorFeedback(
                $this->speechFirstFeedback((string) ($verdict['retry_reason'] ?? ''), $example, $feedbackLanguageCode),
                $pass,
                $feedbackLanguageCode,
            )
            : '';

        if ($shouldRetry && $normalizationConfidence === 'low' && $retryReason === '') {
            $retryReason = $normalizationNote !== ''
                ? $normalizationNote
                : $this->localizedMessage('low_confidence', $feedbackLanguageCode);
        }

        if ($shouldRetry && $retryReason === '') {
            $retryReason = $strictAcceptedPhraseMiss
                ? $this->localizedMessage('strict_goal_phrase', $feedbackLanguageCode)
                : $this->localizedMessage('try_once_more', $feedbackLanguageCode);
        }

        $canContinue = $pass && ! $shouldRetry;
        $suggestedResponse = trim((string) ($verdict['suggested_response'] ?? ($canContinue ? '' : ($example !== '' ? $example : ($verdict['corrected'] ?? '')))));
        if ($this->equivalentSpokenText($suggestedResponse, $normalizedTranscript)) {
            $suggestedResponse = '';
        }
        $feedback = $this->cleanTutorFeedback(
            $this->speechFirstFeedback((string) ($verdict['feedback'] ?? $this->localizedMessage('try_that_again', $feedbackLanguageCode)), $example, $feedbackLanguageCode),
            $pass,
            $feedbackLanguageCode,
        );
        $feedback = $this->withoutRedundantSuggestion($feedback, $normalizedTranscript, $feedbackLanguageCode);
        $feedback = $this->withoutContradictingInterpretation($feedback, $normalizedTranscript, $pass, $feedbackLanguageCode);

        return [
            'transcript' => $transcript,
            'normalized_transcript' => $normalizedTranscript,
            'normalization_confidence' => $normalizationConfidence,
            'normalization_note' => $normalizationNote,
            'pass' => $pass,
            'can_continue' => $canContinue,
            'should_retry' => $shouldRetry,
            'retry_reason' => $retryReason,
            'feedback' => $feedback,
            'corrected' => $normalizedTranscript,
            'suggested_response' => $suggestedResponse,
            'suggestion' => $suggestedResponse,
            'communication' => $this->communicationFromVerdict($verdict, $pass, $feedbackLanguageCode),
            'interpretation' => [
                'confidence' => $interpretation['confidence'],
                'note' => $interpretation['note'],
                'matched_phrase' => $interpretation['matched_phrase'],
                'source' => $interpretation['source'],
            ],
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
    private function communicationFromVerdict(array $verdict, bool $passed, string $feedbackLanguageCode = 'en'): array
    {
        $intentMatch = (string) ($verdict['intent_match'] ?? ($passed ? 'full' : 'partial'));
        $focus = (string) ($verdict['improvement_focus'] ?? ($passed ? 'none' : 'task'));

        return [
            'intent_match' => in_array($intentMatch, ['full', 'partial', 'off_topic'], true) ? $intentMatch : ($passed ? 'full' : 'partial'),
            'understood_meaning' => (bool) ($verdict['understood_meaning'] ?? $passed),
            'went_off_script' => (bool) ($verdict['went_off_script'] ?? false),
            'note' => $this->cleanTutorFeedback(
                $this->speechFirstFeedback((string) ($verdict['communication_note'] ?? ($passed ? $this->localizedMessage('goal_answered', $feedbackLanguageCode) : $this->localizedMessage('goal_partial', $feedbackLanguageCode))), '', $feedbackLanguageCode),
                $passed,
                $feedbackLanguageCode,
            ),
            'improvement_focus' => in_array($focus, ['grammar', 'vocabulary', 'pronunciation', 'coherence', 'task', 'none'], true) ? $focus : ($passed ? 'none' : 'task'),
        ];
    }

    private function cleanTutorFeedback(string $feedback, bool $passed, string $feedbackLanguageCode = 'en'): string
    {
        if ($feedback === '') {
            return '';
        }

        $internalTerms = [
            'allowed phrase',
            'allowed phrases',
            'expected answer',
            'model answer',
            'model list',
            'model phrase',
            'npc line',
            'prompt',
            'rubric',
            'schema',
        ];

        if (! str($feedback)->lower()->contains($internalTerms)) {
            return $feedback;
        }

        if ($feedbackLanguageCode === 'lt') {
            return $passed
                ? 'Atsakymas tiko šiai situacijai.'
                : 'Pabandykite dar kartą trumpu, aiškiu atsakymu šiai situacijai.';
        }

        return $passed
            ? 'That answer worked for this situation.'
            : 'Try again with a short, clear answer for this situation.';
    }

    private function withoutRedundantSuggestion(string $feedback, string $normalizedTranscript, string $feedbackLanguageCode = 'en'): string
    {
        if ($feedback === '' || $normalizedTranscript === '') {
            return $feedback;
        }

        $sameSuggestionPatterns = [
            '/\s*(A more natural spoken version is|More natural|Better|Try saying):\s*[“"]?'.preg_quote($normalizedTranscript, '/').'[”"]?\.?/iu',
            '/\s*(Natūralesnis pasakymas būtų|Natūraliau|Pabandykite sakyti):\s*[„"]?'.preg_quote($normalizedTranscript, '/').'[“"]?\.?/iu',
        ];

        $cleaned = trim((string) preg_replace($sameSuggestionPatterns, '', $feedback));

        if ($cleaned !== '') {
            return $cleaned;
        }

        return $feedbackLanguageCode === 'lt'
            ? 'Atsakymas tiko šiai situacijai.'
            : 'That answer worked for this situation.';
    }

    private function withoutContradictingInterpretation(string $feedback, string $normalizedTranscript, bool $passed, string $feedbackLanguageCode = 'en'): string
    {
        if ($feedback === '' || $normalizedTranscript === '') {
            return $feedback;
        }

        preg_match_all('/[\'"“”‘’]([\p{L}]{2,})[\'"“”‘’]/u', $feedback, $matches);
        if (($matches[1] ?? []) === []) {
            return $feedback;
        }

        $normalizedWords = collect(explode(' ', $this->comparisonText($normalizedTranscript)))
            ->filter()
            ->values();

        foreach ($matches[1] as $quotedWord) {
            $quoted = $this->comparisonText($quotedWord);
            if ($quoted === '' || $normalizedWords->contains($quoted)) {
                continue;
            }

            $nearInterpretedWord = $normalizedWords->contains(
                fn (string $word): bool => abs(strlen($word) - strlen($quoted)) <= 2 && levenshtein($word, $quoted) <= 2,
            );

            if ($nearInterpretedWord) {
                return $this->cleanTutorFeedback('', $passed, $feedbackLanguageCode)
                    ?: ($feedbackLanguageCode === 'lt' ? 'Atsakymas buvo suprantamas.' : 'That answer was understandable.');
            }
        }

        return $feedback;
    }

    private function equivalentSpokenText(string $first, string $second): bool
    {
        if ($first === '' || $second === '') {
            return false;
        }

        return $this->comparisonText($first) === $this->comparisonText($second);
    }

    private function comparisonText(string $value): string
    {
        return str($value)
            ->lower()
            ->replaceMatches('/[^\p{L}\p{N}\s]+/u', ' ')
            ->squish()
            ->toString();
    }

    private function evaluationModeInstruction(bool $strict, string $contentCefrLevel, string $targetLanguageName): string
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
            : "NORMAL. Pass understandable {$targetLanguageName} that satisfies the task at the content level, even with minor spoken grammar or phrasing slips.";

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

    private function normalizationConfidence(mixed $value): string
    {
        return in_array($value, ['high', 'medium', 'low'], true) ? $value : 'high';
    }

    private function lowerConfidence(string $first, string $second): string
    {
        $rank = ['low' => 0, 'medium' => 1, 'high' => 2];

        return ($rank[$first] ?? 2) <= ($rank[$second] ?? 2) ? $first : $second;
    }

    private function speechFirstFeedback(string $feedback, string $example, string $feedbackLanguageCode = 'en'): string
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

        if ($feedbackLanguageCode === 'lt') {
            return $example !== ''
                ? "Buvo suprantama. Natūralesnis pasakymas būtų: {$example}"
                : 'Buvo suprantama. Pabandykite frazę pasakyti šiek tiek aiškiau ir natūraliau.';
        }

        return $example !== ''
            ? "That was understandable. A more natural spoken version is: {$example}"
            : 'That was understandable. Try saying the phrase a little more clearly and naturally.';
    }

    private function localizedMessage(string $key, string $feedbackLanguageCode): string
    {
        $messages = [
            'lt' => [
                'no_speech_detected' => 'Kalbos nepavyko aptikti.',
                'speak_louder' => 'Nieko neišgirdau. Pabandykite kalbėti šiek tiek garsiau.',
                'not_understood' => 'Dar nepavyko suprasti jūsų atsakymo.',
                'low_confidence' => 'Negaliu užtikrintai pasakyti, ką išgirdau. Pabandykite dar kartą šiek tiek aiškiau.',
                'try_once_more' => 'Pabandykite dar kartą prieš tęsdami.',
                'try_that_again' => 'Pabandykite dar kartą.',
                'goal_answered' => 'Aiškiai atsakėte į užduotį.',
                'goal_partial' => 'Atsakymas susijęs, bet dar nevisiškai įvykdo užduotį.',
                'strict_goal_phrase' => 'Šiame režime pabandykite pavartoti tikslinę šios užduoties frazę.',
            ],
            'en' => [
                'no_speech_detected' => 'No speech was detected.',
                'speak_louder' => 'I could not hear anything, try speaking a little louder.',
                'not_understood' => 'I could not understand the spoken answer yet.',
                'low_confidence' => 'I could not confidently hear that. Please try again a little more clearly.',
                'try_once_more' => 'Try once more before continuing.',
                'try_that_again' => 'Try that again.',
                'goal_answered' => 'You answered the goal clearly.',
                'goal_partial' => 'Your answer was related, but did not fully answer the goal.',
                'strict_goal_phrase' => 'In strict mode, try using the target phrase for this goal.',
            ],
        ];

        return $messages[$feedbackLanguageCode][$key] ?? $messages['en'][$key] ?? '';
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
