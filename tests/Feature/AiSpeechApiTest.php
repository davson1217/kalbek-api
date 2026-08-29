<?php

namespace Tests\Feature;

use App\Ai\Agents\SpeakingJudge;
use App\Models\User;
use Database\Seeders\CharacterSeeder;
use Database\Seeders\RestaurantScenarioSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
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

    public function test_fake_text_to_speech_returns_local_audio_without_provider_call(): void
    {
        Config::set('services.kalbek.ai_mode', 'fake');
        Audio::fake([base64_encode('provider-audio')]);

        $response = $this->get('/api/v1/tts?text=Laba%20diena');

        $response
            ->assertOk()
            ->assertHeader('content-type', 'audio/wav');

        $this->assertStringStartsWith('RIFF', $response->content());
        Audio::assertNothingGenerated();
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
            'scores' => [
                'grammar' => 82,
                'vocabulary' => 78,
                'cohesion' => 74,
                'task_completion' => 90,
                'pronunciation' => null,
            ],
            'attempt_cefr_level' => 'a1',
        ]]);
        $this->seed([CharacterSeeder::class, RestaurantScenarioSeeder::class]);

        $response = $this->postJson('/api/v1/speak-check', [
            'scenario_id' => 'restoranas',
            'scene_id' => 'atvykimas',
            'goal_id' => 'staliukas',
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
            ->assertJsonPath('suggestion', 'Norėčiau staliuko dviem, prašau.')
            ->assertJsonPath('scores.grammar', 82)
            ->assertJsonPath('overall_score', 81)
            ->assertJsonPath('attempt_cefr_level', 'a1');

        $this->assertDatabaseHas('speaking_attempts', [
            'transcript' => 'Norėčiau staliuko dviem.',
            'passed' => true,
            'grammar_score' => 82,
            'vocabulary_score' => 78,
            'cohesion_score' => 74,
            'task_completion_score' => 90,
            'overall_score' => 81,
            'attempt_cefr_level' => 'a1',
        ]);

        Transcription::assertGenerated(fn ($prompt): bool => $prompt->language === 'lt');
        SpeakingJudge::assertPrompted(fn ($prompt): bool => $prompt->contains('Ask for a table'));
    }

    public function test_fake_speech_check_returns_deterministic_feedback_without_provider_calls(): void
    {
        Config::set('services.kalbek.ai_mode', 'fake');
        Config::set('services.kalbek.fake_speech_transcript', 'Ar turite maisto?');
        Sanctum::actingAs(User::factory()->create());
        $this->seed([CharacterSeeder::class, RestaurantScenarioSeeder::class]);
        Transcription::fake(['Provider transcript should not be used.']);
        SpeakingJudge::fake([[
            'pass' => false,
            'feedback' => 'Provider judge should not be used.',
            'corrected' => '',
        ]]);

        $response = $this->postJson('/api/v1/speak-check', [
            'scenario_id' => 'restoranas',
            'scene_id' => 'atvykimas',
            'goal_id' => 'staliukas',
            'audio' => UploadedFile::fake()->createWithContent('recording.wav', str_repeat('a', 4096)),
            'intent' => 'Ask whether food is available.',
            'example' => 'Ar turite maisto?',
            'context' => 'At a Lithuanian restaurant.',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('transcript', 'Ar turite maisto?')
            ->assertJsonPath('pass', true)
            ->assertJsonPath('corrected', 'Ar turite maisto?')
            ->assertJsonPath('suggestion', 'Ar turite maisto?')
            ->assertJsonPath('scores.grammar', 78)
            ->assertJsonPath('overall_score', 79)
            ->assertJsonPath('attempt_cefr_level', 'a1');

        $this->assertDatabaseHas('speaking_attempts', [
            'transcript' => 'Ar turite maisto?',
            'passed' => true,
            'overall_score' => 79,
            'attempt_cefr_level' => 'a1',
            'evaluation_provider' => 'fake',
        ]);

        Transcription::assertNothingGenerated();
        SpeakingJudge::assertNeverPrompted();
    }
}
