<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\TextToSpeechSynthesizer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\TextToSpeechRequest;
use App\Models\Language;
use Illuminate\Http\Response;

class TextToSpeechController extends Controller
{
    public function __invoke(TextToSpeechRequest $request, TextToSpeechSynthesizer $synthesizer): Response
    {
        $data = $request->validated();
        $text = trim((string) $data['text']);
        $language = Language::query()->where('code', $data['language'] ?? 'lt')->first();
        $audio = $synthesizer->synthesize(
            $text,
            $language?->code ?? 'lt',
            $language?->name ?? 'Lithuanian',
            $language?->default_voice,
        );

        return $this->audioResponse($audio->content, $audio->mimeType);
    }

    private function audioResponse(string $content, string $mimeType): Response
    {
        return response($content, 200, [
            'content-type' => $mimeType,
            'cache-control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
