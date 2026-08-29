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
        ];
    }
}
