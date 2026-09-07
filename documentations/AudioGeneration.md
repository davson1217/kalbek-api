# Audio Generation and Caching

Kalbek uses text-to-speech audio for authored target-language content, especially NPC opening lines and replies. The current implementation is lazy: audio is generated only when a learner or browser requests a specific line for the first time.

## Current Request Flow

1. The frontend calls `GET /api/v1/tts` with:
   - `text`: the exact target-language line to play.
   - `language`: the target language code, for example `lt`.
2. `App\Http\Controllers\Api\V1\TextToSpeechController` validates the request through `TextToSpeechRequest`.
3. Laravel resolves the language from the `languages.code` column.
4. `LaravelAiTextToSpeechSynthesizer` builds a deterministic cache key from:
   - `languageCode`
   - configured TTS model from `KALBEK_TTS_MODEL`
   - voice, either `languages.default_voice` or `default-female`
   - exact text
5. Redis/Laravel cache is checked for metadata under:
   - `tts:generated-audio:{cacheKey}`
6. If Redis has metadata, Laravel checks the stored file path and streams the audio if the file exists.
7. If Redis misses, MySQL is checked through the `generated_audio.cache_key` column.
8. If MySQL has a matching row and the file exists, Laravel hydrates Redis with metadata and streams the file.
9. If the audio does not exist, Laravel acquires a generation lock:
   - `tts:generate:{cacheKey}`
10. The lock holder calls the configured AI/TTS provider, stores the audio file, writes `generated_audio`, refreshes Redis metadata, and returns the audio.
11. Concurrent requests for the same uncached line wait for the lock, re-check cache/storage, and avoid duplicate provider calls where possible.
12. The response includes long-lived browser cache headers:
   - `Cache-Control: public, max-age=31536000, immutable`
   - `Accept-Ranges: bytes`

## Redis Role

Redis is not the durable audio store. It improves speed and protects token spend.

Redis is used for:

- **Metadata lookup cache**: avoids repeatedly querying MySQL for hot audio lines.
- **Generation locks**: prevents multiple users from triggering duplicate TTS generation for the same uncached text at the same time.
- **Future queues**: CMS-driven pre-generation should run as background jobs using `QUEUE_CONNECTION=redis`.
- **Future job progress**: the CMS can display generation progress from temporary Redis state.

Relevant environment values:

- `CACHE_STORE=redis`
- `QUEUE_CONNECTION=redis`
- `REDIS_HOST=redis`
- `KALBEK_TTS_METADATA_CACHE_TTL_SECONDS=86400`
- `KALBEK_TTS_GENERATION_LOCK_SECONDS=60`
- `KALBEK_TTS_GENERATION_LOCK_WAIT_SECONDS=50`
- `KALBEK_GENERATED_AUDIO_DISK=local`
- `KALBEK_GENERATED_AUDIO_PATH=generated-audio`
- `KALBEK_GENERATED_AUDIO_FALLBACK_DISK=local`
- `KALBEK_GENERATED_AUDIO_ALLOW_FALLBACK=false`

## Current Storage Model

Generated audio is stored in two places:

- **MySQL metadata**: `generated_audio`
- **Audio binary**: configured Laravel filesystem disk/path

Important `generated_audio` columns:

- `cache_key`: deterministic hash for lookup.
- `text`: exact text that produced the audio.
- `voice`: selected voice.
- `provider`: configured audio provider.
- `model`: configured TTS model.
- `disk`: Laravel filesystem disk.
- `path`: file path on that disk.
- `mime_type`: response content type, usually `audio/mpeg` or `audio/wav`.
- `bytes`: stored audio size.
- `last_used_at`: updated when cached audio is served.

Default local development values:

```env
KALBEK_GENERATED_AUDIO_DISK=local
KALBEK_GENERATED_AUDIO_PATH=generated-audio
KALBEK_GENERATED_AUDIO_FALLBACK_DISK=local
KALBEK_GENERATED_AUDIO_ALLOW_FALLBACK=false
```

With those defaults, files are written to Laravel's `local` disk under `storage/app/private/generated-audio`.

For staging or production, point generated audio at an S3-compatible disk:

```env
KALBEK_GENERATED_AUDIO_DISK=s3
KALBEK_GENERATED_AUDIO_PATH=generated-audio/staging
KALBEK_GENERATED_AUDIO_FALLBACK_DISK=local
KALBEK_GENERATED_AUDIO_ALLOW_FALLBACK=false
```

Keep `FILESYSTEM_DISK=local` unless the whole application should use S3 as its default disk. Generated audio has its own disk setting so it can move to object storage without changing unrelated file behavior.

The write path verifies that the file was actually stored before saving `generated_audio`. If the primary disk fails and fallback is disabled, the request fails and no metadata row is written for a missing file.

Fallback can be useful during local development when testing S3-compatible credentials:

```env
KALBEK_GENERATED_AUDIO_DISK=s3
KALBEK_GENERATED_AUDIO_FALLBACK_DISK=local
KALBEK_GENERATED_AUDIO_ALLOW_FALLBACK=true
```

Do not silently enable fallback in production unless there is monitoring around it. A local fallback on a multi-instance deployment can hide object-storage outages and create files that other instances cannot read.

## Production Storage Recommendation

Do not rely on container-local disk for multi-instance production. If the app runs on multiple servers or disposable containers, one instance may generate an audio file that another instance cannot read.

Recommended production model:

- Keep `generated_audio` in MySQL as the source of truth for metadata.
- Store audio binaries on shared object storage such as S3, Cloudflare R2, or an equivalent service.
- Use Redis for metadata cache, locks, queues, and job progress.
- Put a CDN in front of object storage for faster global delivery.
- Keep deterministic cache keys based on language, provider/model, voice, and exact text.
- Regenerate audio only when one of those inputs changes.

For Laravel Cloud Object Storage or Cloudflare R2, use the existing Laravel `s3` disk with S3-compatible credentials:

```env
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=auto
AWS_BUCKET=kalbek-staging-audio
AWS_ENDPOINT=https://<account-id>.r2.cloudflarestorage.com
AWS_USE_PATH_STYLE_ENDPOINT=true
KALBEK_GENERATED_AUDIO_DISK=s3
KALBEK_GENERATED_AUDIO_PATH=generated-audio/staging
KALBEK_GENERATED_AUDIO_ALLOW_FALLBACK=false
```

For AWS S3:

```env
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=eu-central-1
AWS_BUCKET=kalbek-staging-audio
AWS_ENDPOINT=
AWS_USE_PATH_STYLE_ENDPOINT=false
KALBEK_GENERATED_AUDIO_DISK=s3
KALBEK_GENERATED_AUDIO_PATH=generated-audio/staging
KALBEK_GENERATED_AUDIO_ALLOW_FALLBACK=false
```

## CMS Pre-Generation Direction

The CMS currently shows an audio preparation skeleton on the scenario detail page. It does not generate audio yet.

The intended future behavior:

1. Editor opens a published scenario in the CMS.
2. CMS shows missing/stale audio counts for opening lines, goal replies, and props/menu text.
3. Editor clicks `Generate audio`.
4. Laravel dispatches queued jobs, for example `GenerateScenarioAudioJob`.
5. Redis queue workers process each line.
6. Each line still uses the same cache key and generation lock.
7. Job progress is shown in the CMS.
8. Failed lines can be retried without regenerating successful lines.

Pre-generation should stay explicit and selective. Draft content and rarely used content should not burn TTS tokens automatically.
