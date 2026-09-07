<?php

namespace App\Http\Controllers\Api\V1;

use App\CefrLevel;
use App\Contracts\DialogueOrchestratorContract;
use App\Contracts\SpeechEvaluatorContract;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SpeechCheckRequest;
use App\Models\Goal;
use App\Models\Scenario;
use App\Models\SpeakingAttempt;
use App\Models\User;
use App\Modules\Subscriptions\SubscriptionManager;
use App\SpeakingAttemptStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class SpeechCheckController extends Controller
{
    public function __invoke(
        SpeechCheckRequest $request,
        SpeechEvaluatorContract $evaluator,
        DialogueOrchestratorContract $orchestrator,
        SubscriptionManager $subscriptions,
    ): JsonResponse {
        $data = $request->validated();
        /** @var User $user */
        $user = $request->user();
        $access = $subscriptions->accessFor($user);

        if (! $access->canUseAi) {
            return response()->json([
                'message' => 'Your free trial has ended. Subscribe to continue speaking practice.',
                'subscription' => $access->toArray(),
            ], 402);
        }

        $scenario = Scenario::query()->with('language')->where('slug', $data['scenario_id'])->firstOrFail();
        $scene = $scenario->scenes()->where('slug', $data['scene_id'])->first();

        if (! $scene) {
            throw ValidationException::withMessages([
                'scene_id' => 'The selected scene does not belong to the selected scenario.',
            ]);
        }

        $goal = Goal::query()
            ->where('scene_id', $scene->id)
            ->where('slug', $data['goal_id'])
            ->first();

        if (! $goal) {
            throw ValidationException::withMessages([
                'goal_id' => 'The selected goal does not belong to the selected scene.',
            ]);
        }

        $targetLanguageCode = $scenario->language?->code ?? 'lt';
        $targetLanguageName = $scenario->language?->name ?? 'Lithuanian';
        $profile = $user->profile()->first();
        $feedbackLanguageCode = $profile?->app_language ?? 'en';
        $feedbackLanguageName = match ($feedbackLanguageCode) {
            'lt' => 'Lithuanian',
            default => 'English',
        };
        $contentCefrLevel = $goal->cefr_level?->value
            ?? $scene->cefr_level?->value
            ?? $scenario->cefr_level?->value
            ?? CefrLevel::A1->value;
        $learnerLanguageLevel = $user->languageLevels()
            ->where('language_code', $targetLanguageCode)
            ->first();
        $learnerCefrLevel = $learnerLanguageLevel?->current_cefr_level?->value ?? CefrLevel::PreA1->value;

        $result = $evaluator->evaluate(
            $data['audio'],
            $data['intent'],
            $data['example'] ?? '',
            $data['context'] ?? '',
            (bool) $user->strict_speech_mode,
            $contentCefrLevel,
            $learnerCefrLevel,
            $targetLanguageCode,
            $targetLanguageName,
            $feedbackLanguageCode,
            $feedbackLanguageName,
        );

        $canContinue = (bool) ($result['can_continue'] ?? ($result['pass'] && ! ($result['should_retry'] ?? false)));

        $scenario->loadMissing(['scenes.goals.nextScene', 'scenes.npcLines.triggerGoal']);
        $dialogue = $orchestrator->decide($scenario, $scene, $goal, $result['transcript'], $canContinue);

        SpeakingAttempt::query()->create([
            'user_id' => $user->id,
            'scenario_id' => $scenario->id,
            'scene_id' => $scene->id,
            'goal_id' => $goal->id,
            'status' => SpeakingAttemptStatus::Graded,
            'transcript' => $result['transcript'],
            'passed' => $canContinue,
            'score' => $result['overall_score'],
            'grammar_score' => $result['scores']['grammar'],
            'vocabulary_score' => $result['scores']['vocabulary'],
            'cohesion_score' => $result['scores']['cohesion'],
            'task_completion_score' => $result['scores']['task_completion'],
            'pronunciation_score' => $result['scores']['pronunciation'],
            'overall_score' => $result['overall_score'],
            'attempt_cefr_level' => $result['attempt_cefr_level'],
            'evaluation_provider' => $result['evaluation_provider'],
            'evaluation_model' => $result['evaluation_model'],
            'feedback' => $result['feedback'],
            'corrected_text' => $result['normalized_transcript'] ?? $result['corrected'],
            'metadata' => [
                'intent' => $data['intent'],
                'example' => $data['example'] ?? '',
                'context' => $data['context'] ?? '',
                'strict_speech_mode' => (bool) $user->strict_speech_mode,
                'content_cefr_level' => $contentCefrLevel,
                'learner_cefr_level' => $learnerCefrLevel,
                'target_language_code' => $targetLanguageCode,
                'target_language_name' => $targetLanguageName,
                'feedback_language_code' => $feedbackLanguageCode,
                'feedback_language_name' => $feedbackLanguageName,
                'audio_readiness' => $data['audio_readiness'] ?? null,
                'judge_pass' => $result['pass'],
                'can_continue' => $canContinue,
                'should_retry' => (bool) ($result['should_retry'] ?? (! $canContinue)),
                'retry_reason' => $result['retry_reason'] ?? '',
                'normalized_transcript' => $result['normalized_transcript'] ?? $result['corrected'],
                'normalization_confidence' => $result['normalization_confidence'] ?? 'high',
                'normalization_note' => $result['normalization_note'] ?? '',
                'suggested_response' => $result['suggested_response'] ?? $result['suggestion'] ?? '',
                'communication' => $result['communication'],
                'dialogue' => $dialogue,
            ],
            'evaluated_at' => now(),
            'graded_at' => now(),
        ]);

        return response()->json([...$result, 'can_continue' => $canContinue, 'dialogue' => $dialogue]);
    }
}
