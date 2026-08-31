<?php

namespace App\Services\Ai;

use App\Contracts\TextToSpeechSynthesizer;
use App\Data\SynthesizedAudio;

class FakeTextToSpeechSynthesizer implements TextToSpeechSynthesizer
{
    public function synthesize(string $text, string $languageCode = 'lt', string $languageName = 'Lithuanian', ?string $voice = null): SynthesizedAudio
    {
        return new SynthesizedAudio($this->wavTone("{$languageCode}|{$voice}|{$text}"), 'audio/wav');
    }

    private function wavTone(string $text): string
    {
        $sampleRate = 16000;
        $durationSeconds = 0.45;
        $samples = (int) ($sampleRate * $durationSeconds);
        $frequency = 440 + (hexdec(substr(hash('sha256', $text), 0, 2)) % 220);
        $data = '';

        for ($i = 0; $i < $samples; $i++) {
            $amplitude = (int) (12000 * sin(2 * M_PI * $frequency * ($i / $sampleRate)));
            $data .= pack('v', $amplitude & 0xFFFF);
        }

        $dataSize = strlen($data);

        return 'RIFF'
            .pack('V', 36 + $dataSize)
            .'WAVEfmt '
            .pack('VvvVVvv', 16, 1, 1, $sampleRate, $sampleRate * 2, 2, 16)
            .'data'
            .pack('V', $dataSize)
            .$data;
    }
}
