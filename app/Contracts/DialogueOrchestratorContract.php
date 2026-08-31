<?php

namespace App\Contracts;

use App\Models\Goal;
use App\Models\Scenario;
use App\Models\Scene;

interface DialogueOrchestratorContract
{
    /**
     * @return array{matched_goal_id: string, additional_goal_ids: array<int, string>, next_scene_id: string|null, reply_scene_id: string|null, reply_trigger_goal_id: string|null, should_complete: bool, reason: string, provider: string|null, model: string|null}
     */
    public function decide(Scenario $scenario, Scene $scene, Goal $selectedGoal, string $transcript, bool $passed): array;
}
