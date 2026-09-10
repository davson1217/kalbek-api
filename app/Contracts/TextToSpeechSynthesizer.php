<?php

namespace App\Contracts;

use App\Data\SynthesizedAudio;

interface TextToSpeechSynthesizer
{
    public function synthesize(string $text, string $languageCode = 'lt', string $languageName = 'Lithuanian', ?string $voice = null, ?string $speakingStyle = null): SynthesizedAudio;
}
