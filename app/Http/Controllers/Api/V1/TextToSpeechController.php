<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\TextToSpeechRequest;
use App\Models\GeneratedAudio;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Audio;

class TextToSpeechController extends Controller
{
    public function __invoke(TextToSpeechRequest $request): Response
    {
        $text = trim((string) $request->validated('text'));
        $voice = 'default-female';
        $model = config('services.kalbek.tts_model', 'gpt-4o-mini-tts');
        $cacheKey = hash('sha256', "{$model}|{$voice}|{$text}");

        $cached = GeneratedAudio::query()->where('cache_key', $cacheKey)->first();

        if ($cached && Storage::disk($cached->disk)->exists($cached->path)) {
            $cached->update(['last_used_at' => now()]);

            return $this->audioResponse(
                Storage::disk($cached->disk)->get($cached->path),
                $cached->mime_type,
            );
        }

        $audio = Audio::of($text)
            ->voice($voice)
            ->instructions('Speak in clear, natural Lithuanian with correct Lithuanian pronunciation and stress. Warm, friendly, and slightly slow, like a patient native speaker in an everyday conversation.')
            ->timeout(45)
            ->generate(model: $model);

        $content = $audio->content();
        $mimeType = $audio->mimeType() ?? 'audio/mpeg';
        $path = "generated-audio/{$cacheKey}.mp3";

        Storage::disk('local')->put($path, $content);

        GeneratedAudio::query()->updateOrCreate(
            ['cache_key' => $cacheKey],
            [
                'text' => $text,
                'voice' => $voice,
                'provider' => config('ai.default_for_audio'),
                'model' => $model,
                'disk' => 'local',
                'path' => $path,
                'mime_type' => $mimeType,
                'bytes' => strlen($content),
                'last_used_at' => now(),
            ],
        );

        return $this->audioResponse($content, $mimeType);
    }

    private function audioResponse(string $content, string $mimeType): Response
    {
        return response($content, 200, [
            'content-type' => $mimeType,
            'cache-control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
