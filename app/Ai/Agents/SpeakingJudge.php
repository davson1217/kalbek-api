<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

class SpeakingJudge implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return "You judge a learner's spoken Lithuanian in a role-play conversation. "
            .'Decide by meaning: if what they said would work in the situation and a Lithuanian '
            .'speaker would understand it, it passes, even with small grammar, case, or accent slips. '
            .'Fail only if it is off-topic, not Lithuanian, or unintelligible. '
            .'Score grammar, vocabulary, cohesion, and task completion from 0 to 100. '
            .'Use null for pronunciation unless audio-level evidence is explicitly available. '
            .'Estimate the attempt CEFR level as pre_a1, a1, a2, b1, b2, c1, or c2. '
            .'Feedback must be one short friendly English sentence and mention one specific fix if useful. '
            ."Corrected must be the learner's sentence written in correct natural Lithuanian.";
    }

    /**
     * Get the agent's structured output schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'pass' => $schema->boolean()->required(),
            'feedback' => $schema->string()->required(),
            'corrected' => $schema->string()->required(),
            'scores' => $schema->object([
                'grammar' => $schema->integer()->min(0)->max(100)->required(),
                'vocabulary' => $schema->integer()->min(0)->max(100)->required(),
                'cohesion' => $schema->integer()->min(0)->max(100)->required(),
                'task_completion' => $schema->integer()->min(0)->max(100)->required(),
                'pronunciation' => $schema->integer()->min(0)->max(100)->nullable(),
            ])->required(),
            'attempt_cefr_level' => $schema->string()->enum(['pre_a1', 'a1', 'a2', 'b1', 'b2', 'c1', 'c2'])->required(),
        ];
    }
}
