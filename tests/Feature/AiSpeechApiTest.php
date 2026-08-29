<?php

namespace Tests\Feature;

use App\Ai\Agents\SpeakingJudge;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Audio;
use Laravel\Ai\Transcription;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AiSpeechApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_text_to_speech_generates_and_caches_audio(): void
    {
        Storage::fake('local');
        Audio::fake([base64_encode('fake-audio-content')]);

        $first = $this->getJson('/api/v1/tts?text=Laba%20diena');

        $first
            ->assertOk()
            ->assertHeader('content-type', 'audio/mpeg');

        Audio::assertGenerated(fn ($prompt): bool => $prompt->contains('Laba diena'));
        $this->assertDatabaseHas('generated_audio', [
            'text' => 'Laba diena',
            'bytes' => strlen('fake-audio-content'),
        ]);

        $second = $this->get('/api/v1/tts?text=Laba%20diena');

        $second
            ->assertOk()
            ->assertContent('fake-audio-content');
    }

    public function test_text_to_speech_validates_text(): void
    {
        $this->getJson('/api/v1/tts')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('text');
    }

    public function test_speech_check_requires_authentication(): void
    {
        $this->postJson('/api/v1/speak-check')
            ->assertUnauthorized();
    }

    public function test_speech_check_transcribes_and_judges_audio(): void
    {
        Sanctum::actingAs(User::factory()->create());
        Transcription::fake(['Norėčiau staliuko dviem.']);
        SpeakingJudge::fake([[
            'pass' => true,
            'feedback' => 'That works well.',
            'corrected' => 'Norėčiau staliuko dviem.',
        ]]);

        $response = $this->postJson('/api/v1/speak-check', [
            'audio' => UploadedFile::fake()->createWithContent('recording.wav', str_repeat('a', 4096)),
            'intent' => 'Ask for a table for two people.',
            'example' => 'Norėčiau staliuko dviem, prašau.',
            'context' => 'At a Lithuanian restaurant.',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('transcript', 'Norėčiau staliuko dviem.')
            ->assertJsonPath('pass', true)
            ->assertJsonPath('feedback', 'That works well.')
            ->assertJsonPath('corrected', 'Norėčiau staliuko dviem.')
            ->assertJsonPath('suggestion', 'Norėčiau staliuko dviem, prašau.');

        Transcription::assertGenerated(fn ($prompt): bool => $prompt->language === 'lt');
        SpeakingJudge::assertPrompted(fn ($prompt): bool => $prompt->contains('Ask for a table'));
    }
}
