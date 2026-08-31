<?php

namespace App\Services\Ai;

use App\Contracts\DialogueOrchestratorContract;
use App\Models\Goal;
use App\Models\Scenario;
use App\Models\Scene;

class FakeDialogueOrchestrator implements DialogueOrchestratorContract
{
    public function decide(Scenario $scenario, Scene $scene, Goal $selectedGoal, string $transcript, bool $passed): array
    {
        return app(DialogueOrchestrator::class)->heuristicDecision($scenario, $scene, $selectedGoal, $transcript, $passed, 'fake');
    }
}
