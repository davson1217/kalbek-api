<?php

namespace App\Contracts;

use App\Data\SynthesizedAudio;

interface TextToSpeechSynthesizer
{
    public function synthesize(string $text): SynthesizedAudio;
}
