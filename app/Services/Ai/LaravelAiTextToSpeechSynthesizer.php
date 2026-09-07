<?php

namespace App\Services\Ai;

use App\Contracts\TextToSpeechSynthesizer;
use App\Data\SynthesizedAudio;
use App\Exceptions\AudioGenerationInProgress;
use App\Models\GeneratedAudio;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Audio;

class LaravelAiTextToSpeechSynthesizer implements TextToSpeechSynthesizer
{
    public function synthesize(string $text, string $languageCode = 'lt', string $languageName = 'Lithuanian', ?string $voice = null): SynthesizedAudio
    {
        $voice ??= 'default-female';
        $model = config('services.kalbek.tts_model', 'gpt-4o-mini-tts');
        $cacheKey = hash('sha256', "{$languageCode}|{$model}|{$voice}|{$text}");

        if ($cached = $this->cachedAudio($cacheKey)) {
            return $cached;
        }

        try {
            return Cache::lock(
                "tts:generate:{$cacheKey}",
                (int) config('services.kalbek.tts_generation_lock_seconds', 60),
            )->block(
                (int) config('services.kalbek.tts_generation_lock_wait_seconds', 50),
                fn (): SynthesizedAudio => $this->synthesizeAfterLock(
                    $cacheKey,
                    $languageName,
                    $model,
                    $text,
                    $voice,
                ),
            );
        } catch (LockTimeoutException) {
            if ($cached = $this->cachedAudio($cacheKey)) {
                return $cached;
            }

            throw new AudioGenerationInProgress;
        }
    }

    private function synthesizeAfterLock(string $cacheKey, string $languageName, string $model, string $text, string $voice): SynthesizedAudio
    {
        if ($cached = $this->cachedAudio($cacheKey)) {
            return $cached;
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

        $generatedAudio = GeneratedAudio::query()->updateOrCreate(
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
        $this->putCachedMetadata($generatedAudio);

        return new SynthesizedAudio($content, $mimeType);
    }

    private function cachedAudio(string $cacheKey): ?SynthesizedAudio
    {
        $metadata = Cache::get($this->metadataCacheKey($cacheKey));

        if (! is_array($metadata)) {
            $generatedAudio = GeneratedAudio::query()->where('cache_key', $cacheKey)->first();

            if (! $generatedAudio) {
                return null;
            }

            $metadata = $this->metadata($generatedAudio);
            $this->putCachedMetadata($generatedAudio);
        }

        $disk = (string) ($metadata['disk'] ?? 'local');
        $path = (string) ($metadata['path'] ?? '');

        if ($path === '' || ! Storage::disk($disk)->exists($path)) {
            Cache::forget($this->metadataCacheKey($cacheKey));

            return null;
        }

        GeneratedAudio::query()
            ->where('cache_key', $cacheKey)
            ->update(['last_used_at' => now()]);

        $audio = $this->browserPlayableAudio(
            Storage::disk($disk)->get($path),
            (string) ($metadata['mime_type'] ?? 'audio/mpeg'),
        );

        return new SynthesizedAudio($audio->content, $audio->mimeType);
    }

    private function putCachedMetadata(GeneratedAudio $generatedAudio): void
    {
        Cache::put(
            $this->metadataCacheKey($generatedAudio->cache_key),
            $this->metadata($generatedAudio),
            (int) config('services.kalbek.tts_metadata_cache_ttl_seconds', 86400),
        );
    }

    /**
     * @return array{disk: string, path: string, mime_type: string}
     */
    private function metadata(GeneratedAudio $generatedAudio): array
    {
        return [
            'disk' => $generatedAudio->disk,
            'path' => $generatedAudio->path,
            'mime_type' => $generatedAudio->mime_type,
        ];
    }

    private function metadataCacheKey(string $cacheKey): string
    {
        return "tts:generated-audio:{$cacheKey}";
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
