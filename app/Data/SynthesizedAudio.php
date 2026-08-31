<?php

namespace App\Data;

final readonly class SynthesizedAudio
{
    public function __construct(
        public string $content,
        public string $mimeType,
    ) {}
}
