<?php

namespace App\Services\Ai;

use App\Contracts\TextToSpeechSynthesizer;
use App\Data\SynthesizedAudio;
use App\Models\GeneratedAudio;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Audio;

class LaravelAiTextToSpeechSynthesizer implements TextToSpeechSynthesizer
{
    public function synthesize(string $text, string $languageCode = 'lt', string $languageName = 'Lithuanian', ?string $voice = null): SynthesizedAudio
    {
        $voice ??= 'default-female';
        $model = config('services.kalbek.tts_model', 'gpt-4o-mini-tts');
        $cacheKey = hash('sha256', "{$languageCode}|{$model}|{$voice}|{$text}");

        $cached = GeneratedAudio::query()->where('cache_key', $cacheKey)->first();

        if ($cached && Storage::disk($cached->disk)->exists($cached->path)) {
            $cached->update(['last_used_at' => now()]);
            $audio = $this->browserPlayableAudio(
                Storage::disk($cached->disk)->get($cached->path),
                $cached->mime_type,
            );

            return new SynthesizedAudio($audio->content, $audio->mimeType);
        }

        $audio = Audio::of($text)
            ->voice($voice)
            ->instructions("Speak in clear, natural {$languageName} with correct {$languageName} pronunciation and stress. Warm, friendly, and slightly slow, like a patient native speaker in an everyday conversation.")
            ->timeout(45)
            ->generate(model: $model);

        $audio = $this->browserPlayableAudio($audio->content(), $audio->mimeType() ?? 'audio/mpeg');
        $content = $audio->content;
        $mimeType = $audio->mimeType;
        $extension = $mimeType === 'audio/wav' ? 'wav' : 'mp3';
        $path = "generated-audio/{$cacheKey}.{$extension}";

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

        return new SynthesizedAudio($content, $mimeType);
    }

    private function browserPlayableAudio(string $content, string $mimeType): SynthesizedAudio
    {
        if ($mimeType !== 'audio/pcm') {
            return new SynthesizedAudio($content, $mimeType);
        }

        return new SynthesizedAudio($this->pcmToWav($content), 'audio/wav');
    }

    private function pcmToWav(string $pcm): string
    {
        $sampleRate = (int) config('services.kalbek.tts_pcm_sample_rate', 24000);
        $dataSize = strlen($pcm);

        return 'RIFF'
            .pack('V', 36 + $dataSize)
            .'WAVEfmt '
            .pack('VvvVVvv', 16, 1, 1, $sampleRate, $sampleRate * 2, 2, 16)
            .'data'
            .pack('V', $dataSize)
            .$pcm;
    }
}
