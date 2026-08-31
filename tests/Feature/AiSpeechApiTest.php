<?php

namespace Tests\Feature;

use App\Ai\Agents\DialogueRouter;
use App\Ai\Agents\SpeakingJudge;
use App\Models\Goal;
use App\Models\NpcLine;
use App\Models\Scenario;
use App\Models\Scene;
use App\Models\User;
use Database\Seeders\A1ScenarioSeeder;
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
            'intent_match' => 'full',
            'understood_meaning' => true,
            'went_off_script' => true,
            'communication_note' => 'You answered the goal and added a natural detail.',
            'improvement_focus' => 'grammar',
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
            'goal_id' => 'ask-table-two',
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
            ->assertJsonPath('communication.intent_match', 'full')
            ->assertJsonPath('communication.understood_meaning', true)
            ->assertJsonPath('communication.went_off_script', true)
            ->assertJsonPath('communication.note', 'You answered the goal and added a natural detail.')
            ->assertJsonPath('communication.improvement_focus', 'grammar')
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
            'metadata->communication->intent_match' => 'full',
            'metadata->communication->went_off_script' => true,
        ]);

        Transcription::assertGenerated(fn ($prompt): bool => $prompt->language === 'lt');
        SpeakingJudge::assertPrompted(fn ($prompt): bool => $prompt
            ->contains('Ask for a table')
            && $prompt->contains('Judge whether the current communicative goal was answered'));
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
            'goal_id' => 'ask-table',
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
            ->assertJsonPath('communication.intent_match', 'full')
            ->assertJsonPath('communication.note', 'The spoken answer satisfies the current goal.')
            ->assertJsonPath('communication.improvement_focus', 'none')
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

    public function test_speech_check_passes_strict_mode_to_the_speaking_judge(): void
    {
        Sanctum::actingAs(User::factory()->create(['strict_speech_mode' => true]));
        Transcription::fake(['Kavos, prašau.']);
        SpeakingJudge::fake([[
            'pass' => true,
            'feedback' => 'That works well.',
            'corrected' => 'Kavos, prašau.',
            'intent_match' => 'full',
            'understood_meaning' => true,
            'went_off_script' => false,
            'communication_note' => 'You answered the goal clearly.',
            'improvement_focus' => 'none',
            'scores' => [
                'grammar' => 88,
                'vocabulary' => 86,
                'cohesion' => 82,
                'task_completion' => 91,
                'pronunciation' => null,
            ],
            'attempt_cefr_level' => 'a1',
        ]]);
        $this->seed([CharacterSeeder::class, RestaurantScenarioSeeder::class]);

        $this->postJson('/api/v1/speak-check', [
            'scenario_id' => 'restoranas',
            'scene_id' => 'atvykimas',
            'goal_id' => 'ask-table',
            'audio' => UploadedFile::fake()->createWithContent('recording.wav', str_repeat('a', 4096)),
            'intent' => 'Order coffee politely.',
            'example' => 'Kavos, prašau.',
            'context' => 'At a cafe.',
        ])->assertOk();

        SpeakingJudge::assertPrompted(fn ($prompt): bool => $prompt->contains('Evaluation mode: STRICT'));
        $this->assertDatabaseHas('speaking_attempts', [
            'transcript' => 'Kavos, prašau.',
            'metadata->strict_speech_mode' => true,
        ]);
    }

    public function test_speech_check_feedback_avoids_text_formatting_language_for_spoken_answers(): void
    {
        Sanctum::actingAs(User::factory()->create());
        Transcription::fake(['Labadiena, mano vardas devydas.']);
        SpeakingJudge::fake([[
            'pass' => true,
            'feedback' => 'Great effort, just watch the spelling and capitalize the name.',
            'corrected' => 'Laba diena, mano vardas Deivydas.',
            'intent_match' => 'full',
            'understood_meaning' => true,
            'went_off_script' => false,
            'communication_note' => 'The speech worked, but the spelling in the transcript is rough.',
            'improvement_focus' => 'grammar',
            'scores' => [
                'grammar' => 80,
                'vocabulary' => 80,
                'cohesion' => 80,
                'task_completion' => 90,
                'pronunciation' => null,
            ],
            'attempt_cefr_level' => 'a1',
        ]]);
        $this->seed([CharacterSeeder::class, A1ScenarioSeeder::class]);

        $response = $this->postJson('/api/v1/speak-check', [
            'scenario_id' => 'prisistatymas',
            'scene_id' => 'pasisveikinimas',
            'goal_id' => 'intro-name',
            'audio' => UploadedFile::fake()->createWithContent('recording.wav', str_repeat('a', 4096)),
            'intent' => 'The learner says their name.',
            'example' => 'Laba diena, mano vardas Deivydas.',
            'context' => 'Introducing yourself.',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('transcript', 'Labadiena, mano vardas devydas.')
            ->assertJsonPath('feedback', 'That was understandable. A more natural spoken version is: Laba diena, mano vardas Deivydas.')
            ->assertJsonPath('communication.note', 'That was understandable. Try saying the phrase a little more clearly and naturally.');

        SpeakingJudge::assertPrompted(fn ($prompt): bool => $prompt
            ->contains('Raw speech transcript: "Labadiena, mano vardas devydas."')
            && $prompt->contains('Normalized transcript for judging: "Laba diena, mano vardas devydas."')
            && $prompt->contains('Judge the spoken answer, not the transcript formatting.'));
    }

    public function test_speech_check_defaults_missing_communication_metadata_for_older_judge_payloads(): void
    {
        Sanctum::actingAs(User::factory()->create());
        Transcription::fake(['Aš esu David.']);
        SpeakingJudge::fake([[
            'pass' => true,
            'feedback' => 'That works well.',
            'corrected' => 'Aš esu David.',
            'scores' => [
                'grammar' => 80,
                'vocabulary' => 80,
                'cohesion' => 80,
                'task_completion' => 85,
                'pronunciation' => null,
            ],
            'attempt_cefr_level' => 'a1',
        ]]);
        $this->seed([CharacterSeeder::class, A1ScenarioSeeder::class]);

        $this->postJson('/api/v1/speak-check', [
            'scenario_id' => 'prisistatymas',
            'scene_id' => 'pasisveikinimas',
            'goal_id' => 'intro-name',
            'audio' => UploadedFile::fake()->createWithContent('recording.wav', str_repeat('a', 4096)),
            'intent' => 'The learner says their name.',
            'example' => 'Aš esu David.',
            'context' => 'Introducing yourself.',
        ])
            ->assertOk()
            ->assertJsonPath('communication.intent_match', 'full')
            ->assertJsonPath('communication.understood_meaning', true)
            ->assertJsonPath('communication.went_off_script', false)
            ->assertJsonPath('communication.note', 'You answered the goal clearly.')
            ->assertJsonPath('communication.improvement_focus', 'none');
    }

    public function test_speech_check_keeps_authored_progression_even_when_utterance_mentions_later_intent(): void
    {
        Config::set('services.kalbek.ai_mode', 'fake');
        Config::set('services.kalbek.fake_speech_transcript', 'Laba diena, man skauda galvą.');
        Sanctum::actingAs(User::factory()->create());

        $this->seed(CharacterSeeder::class);

        $scenario = Scenario::factory()->create([
            'slug' => 'pharmacy-visit',
            'start_scene_slug' => 'greeting',
        ]);
        $greeting = Scene::factory()->for($scenario)->create(['slug' => 'greeting', 'sort_order' => 10]);
        $symptoms = Scene::factory()->for($scenario)->create(['slug' => 'symptoms', 'sort_order' => 20]);
        $medicine = Scene::factory()->for($scenario)->create(['slug' => 'medicine', 'sort_order' => 30]);

        $sayHello = Goal::factory()->for($greeting)->create([
            'slug' => 'say-hello',
            'label' => 'Greet the pharmacist',
            'intent' => 'The learner greets the pharmacist politely.',
            'example' => 'Laba diena.',
            'next_scene_id' => $symptoms->id,
        ]);
        $headache = Goal::factory()->for($symptoms)->create([
            'slug' => 'headache',
            'label' => 'Say you have a headache',
            'intent' => 'The learner explains that they have a headache.',
            'example' => 'Man skauda galvą.',
            'next_scene_id' => $medicine->id,
        ]);
        NpcLine::factory()->for($greeting)->create([
            'trigger_goal_id' => $sayHello->id,
            'lt' => 'Sveiki. Kas jums yra?',
            'en' => 'Hello. What is wrong?',
            'priority' => 100,
        ]);

        $response = $this->postJson('/api/v1/speak-check', [
            'scenario_id' => $scenario->slug,
            'scene_id' => $greeting->slug,
            'goal_id' => $sayHello->slug,
            'audio' => UploadedFile::fake()->createWithContent('recording.wav', str_repeat('a', 4096)),
            'intent' => $sayHello->intent,
            'example' => $sayHello->example,
            'context' => 'At a pharmacy.',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('pass', true)
            ->assertJsonPath('dialogue.matched_goal_id', 'say-hello')
            ->assertJsonPath('dialogue.additional_goal_ids', [])
            ->assertJsonPath('dialogue.next_scene_id', 'symptoms')
            ->assertJsonPath('dialogue.reply_scene_id', 'greeting')
            ->assertJsonPath('dialogue.reply_trigger_goal_id', 'say-hello')
            ->assertJsonPath('dialogue.should_complete', false);

        $this->assertDatabaseHas('speaking_attempts', [
            'transcript' => 'Laba diena, man skauda galvą.',
            'goal_id' => $sayHello->id,
        ]);
    }

    public function test_dialogue_router_is_not_used_for_authored_goal_progression(): void
    {
        Config::set('services.kalbek.ai_mode', 'live');
        Sanctum::actingAs(User::factory()->create());
        Transcription::fake(['Kiek tai kainuoja?']);
        SpeakingJudge::fake([[
            'pass' => true,
            'feedback' => 'That works well.',
            'corrected' => 'Kiek tai kainuoja?',
            'scores' => [
                'grammar' => 90,
                'vocabulary' => 90,
                'cohesion' => 90,
                'task_completion' => 90,
                'pronunciation' => null,
            ],
            'attempt_cefr_level' => 'a1',
        ]]);
        DialogueRouter::fake();

        $this->seed(CharacterSeeder::class);

        $scenario = Scenario::factory()->create([
            'slug' => 'pharmacy-visit',
            'start_scene_slug' => 'medicine',
        ]);
        $medicine = Scene::factory()->for($scenario)->create(['slug' => 'medicine', 'sort_order' => 10]);
        $payment = Scene::factory()->for($scenario)->create(['slug' => 'payment', 'sort_order' => 20]);
        $askPrice = Goal::factory()->for($medicine)->create([
            'slug' => 'ask-price',
            'label' => 'Ask the price',
            'intent' => 'The learner asks how much the medicine costs.',
            'example' => 'Kiek tai kainuoja?',
            'next_scene_id' => $payment->id,
        ]);
        NpcLine::factory()->for($medicine)->create([
            'trigger_goal_id' => $askPrice->id,
            'lt' => 'Šis vaistas kainuoja šešis eurus.',
            'en' => 'This medicine costs six euros.',
            'priority' => 100,
        ]);

        $response = $this->postJson('/api/v1/speak-check', [
            'scenario_id' => $scenario->slug,
            'scene_id' => $medicine->slug,
            'goal_id' => $askPrice->slug,
            'audio' => UploadedFile::fake()->createWithContent('recording.wav', str_repeat('a', 4096)),
            'intent' => $askPrice->intent,
            'example' => $askPrice->example,
            'context' => 'At a pharmacy.',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('dialogue.next_scene_id', 'payment')
            ->assertJsonPath('dialogue.reply_scene_id', 'medicine')
            ->assertJsonPath('dialogue.reply_trigger_goal_id', 'ask-price')
            ->assertJsonPath('dialogue.should_complete', false)
            ->assertJsonPath('dialogue.reason', 'Advance by the selected authored goal.');

        DialogueRouter::assertNeverPrompted();
    }
}
