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

        return $this->audioResponse($request, $audio->content, $audio->mimeType);
    }

    private function audioResponse(TextToSpeechRequest $request, string $content, string $mimeType): Response
    {
        $size = strlen($content);
        $headers = [
            'content-type' => $mimeType,
            'accept-ranges' => 'bytes',
            'cache-control' => 'public, max-age=31536000, immutable',
            'content-length' => (string) $size,
        ];

        $range = $request->headers->get('Range');

        if (! $range || ! preg_match('/bytes=(\d*)-(\d*)/', $range, $matches)) {
            return response($content, 200, $headers);
        }

        $start = $matches[1] === '' ? 0 : (int) $matches[1];
        $end = $matches[2] === '' ? $size - 1 : (int) $matches[2];

        if ($start >= $size || $end < $start) {
            return response('', 416, [
                ...$headers,
                'content-range' => "bytes */{$size}",
                'content-length' => '0',
            ]);
        }

        $end = min($end, $size - 1);
        $length = $end - $start + 1;

        return response(substr($content, $start, $length), 206, [
            ...$headers,
            'content-length' => (string) $length,
            'content-range' => "bytes {$start}-{$end}/{$size}",
        ]);
    }
}
