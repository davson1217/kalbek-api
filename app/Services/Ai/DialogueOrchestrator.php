<?php

namespace App\Services\Ai;

use App\Contracts\DialogueOrchestratorContract;
use App\Models\Goal;
use App\Models\Scenario;
use App\Models\Scene;

class DialogueOrchestrator implements DialogueOrchestratorContract
{
    public function decide(Scenario $scenario, Scene $scene, Goal $selectedGoal, string $transcript, bool $passed): array
    {
        return $this->heuristicDecision($scenario, $scene, $selectedGoal, $transcript, $passed);
    }

    /** @return array{matched_goal_id: string, additional_goal_ids: array<int, string>, next_scene_id: string|null, reply_scene_id: string|null, reply_trigger_goal_id: string|null, should_complete: bool, reason: string, provider: string|null, model: string|null} */
    public function heuristicDecision(Scenario $scenario, Scene $scene, Goal $selectedGoal, string $transcript, bool $passed, ?string $provider = null): array
    {
        if (! $passed) {
            return $this->decision($selectedGoal->slug, [], $scene->slug, $scene->slug, null, false, 'Selected goal did not pass.', $provider);
        }

        $nextScene = $selectedGoal->nextScene;

        if (! $nextScene) {
            return $this->decision($selectedGoal->slug, [], null, $scene->slug, $selectedGoal->slug, true, 'Selected goal completes the scenario.', $provider);
        }

        return $this->decision($selectedGoal->slug, [], $nextScene->slug, $this->replySceneForSelectedGoal($scene, $nextScene, $selectedGoal), $selectedGoal->slug, false, 'Advance by the selected authored goal.', $provider);
    }

    private function replySceneForSelectedGoal(Scene $scene, Scene $nextScene, Goal $selectedGoal): string
    {
        $scene->loadMissing('npcLines');

        return $scene->npcLines->contains('trigger_goal_id', $selectedGoal->id)
            ? $scene->slug
            : $nextScene->slug;
    }

    /** @return array{matched_goal_id: string, additional_goal_ids: array<int, string>, next_scene_id: string|null, reply_scene_id: string|null, reply_trigger_goal_id: string|null, should_complete: bool, reason: string, provider: string|null, model: string|null} */
    private function decision(string $matchedGoalId, array $additionalGoalIds, ?string $nextSceneId, ?string $replySceneId, ?string $replyTriggerGoalId, bool $shouldComplete, string $reason, ?string $provider): array
    {
        return [
            'matched_goal_id' => $matchedGoalId,
            'additional_goal_ids' => $additionalGoalIds,
            'next_scene_id' => $nextSceneId,
            'reply_scene_id' => $replySceneId,
            'reply_trigger_goal_id' => $replyTriggerGoalId,
            'should_complete' => $shouldComplete,
            'reason' => $reason,
            'provider' => $provider,
            'model' => null,
        ];
    }
}
