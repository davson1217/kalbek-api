<?php

namespace App\Services\Ai;

class SpeechInterpretation
{
    /**
     * @param  array<int, string>  $acceptedPhrases
     * @return array{transcript: string, confidence: string, note: string, matched_phrase: string|null, source: string}
     */
    public function interpret(
        string $rawTranscript,
        string $example = '',
        array $acceptedPhrases = [],
        string $feedbackLanguageCode = 'en',
    ): array {
        $normalizedTranscript = $this->normalizeSmallArtifacts($rawTranscript);
        $candidates = $this->phrases($acceptedPhrases);

        $match = $this->bestPhraseMatch($normalizedTranscript, $candidates);
        if ($match && $match['confidence'] !== 'low') {
            return [
                'transcript' => $match['phrase'],
                'confidence' => $match['confidence'],
                'note' => $match['confidence'] === 'high'
                    ? ''
                    : $this->message('medium_match', $feedbackLanguageCode),
                'matched_phrase' => $match['phrase'],
                'source' => 'accepted_phrase',
            ];
        }

        return [
            'transcript' => $normalizedTranscript,
            'confidence' => 'high',
            'note' => '',
            'matched_phrase' => null,
            'source' => 'transcript',
        ];
    }

    /**
     * @param  array<int, string>  $acceptedPhrases
     * @return array<int, string>
     */
    private function phrases(array $acceptedPhrases): array
    {
        return collect($acceptedPhrases)
            ->map(fn (mixed $phrase): string => trim((string) $phrase))
            ->filter()
            ->unique(fn (string $phrase): string => $this->comparisonText($phrase))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $phrases
     * @return array{phrase: string, confidence: string}|null
     */
    private function bestPhraseMatch(string $transcript, array $phrases): ?array
    {
        if ($transcript === '' || $phrases === []) {
            return null;
        }

        $transcriptKey = $this->comparisonText($transcript);
        $bestPhrase = null;
        $bestPercent = 0.0;

        foreach ($phrases as $phrase) {
            $phraseKey = $this->comparisonText($phrase);
            if ($phraseKey === '') {
                continue;
            }

            if ($phraseKey === $transcriptKey) {
                return ['phrase' => $phrase, 'confidence' => 'high'];
            }

            similar_text($transcriptKey, $phraseKey, $percent);
            if ($percent > $bestPercent) {
                $bestPercent = $percent;
                $bestPhrase = $phrase;
            }
        }

        if (! $bestPhrase) {
            return null;
        }

        return match (true) {
            $bestPercent >= 88 => ['phrase' => $bestPhrase, 'confidence' => 'high'],
            $bestPercent >= 82 => ['phrase' => $bestPhrase, 'confidence' => 'medium'],
            default => ['phrase' => $bestPhrase, 'confidence' => 'low'],
        };
    }

    private function normalizeSmallArtifacts(string $transcript): string
    {
        return str($transcript)
            ->replaceMatches('/\blaba\s*d[iey]a?na\b/iu', 'Laba diena')
            ->replaceMatches('/\blaba\s*diena\b/iu', 'Laba diena')
            ->replaceMatches('/\blabas\s*diena\b/iu', 'Laba diena')
            ->replaceMatches('/\blabadiena\b/iu', 'Laba diena')
            ->replaceMatches('/\blabadiana\b/iu', 'Laba diena')
            ->replaceMatches('/\blabasdiena\b/iu', 'Laba diena')
            ->replaceMatches('/\blaba\s*rytas\b/iu', 'Labas rytas')
            ->replaceMatches('/\blabasrytas\b/iu', 'Labas rytas')
            ->replaceMatches('/\blabas\s*vakaras\b/iu', 'Labas vakaras')
            ->replaceMatches('/\blabasvakaras\b/iu', 'Labas vakaras')
            ->replaceMatches('/\baciu\b/iu', 'Ačiū')
            ->replaceMatches('/\bachiu\b/iu', 'Ačiū')
            ->replaceMatches('/\bprasau\b/iu', 'prašau')
            ->replaceMatches('/\bprasom\b/iu', 'prašom')
            ->replaceMatches('/\bviso\s*gero\b/iu', 'viso gero')
            ->replaceMatches('/\bvisogero\b/iu', 'viso gero')
            ->replaceMatches('/\bnoreciau\b/iu', 'norėčiau')
            ->replaceMatches('/\bmokesiu\b/iu', 'mokėsiu')
            ->toString();
    }

    private function comparisonText(string $value): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;

        return str($ascii)
            ->lower()
            ->replaceMatches('/[^\p{L}\p{N}\s]+/u', ' ')
            ->squish()
            ->toString();
    }

    private function message(string $key, string $feedbackLanguageCode): string
    {
        return match ([$feedbackLanguageCode, $key]) {
            ['lt', 'medium_match'] => 'Tai panašu į pamokos frazę, bet verta pakartoti aiškiai.',
            default => 'That sounded close to the lesson phrase, but it may be worth repeating clearly.',
        };
    }
}
