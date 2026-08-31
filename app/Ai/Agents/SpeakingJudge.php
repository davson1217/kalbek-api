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
            .'Decide by communicative intent: the learner may answer freely and does not need to match the model phrase. '
            .'If their answer satisfies the current goal in the situation, pass it even when it includes extra natural information. '
            .'If their answer is understandable but indirect, over-expanded, or slightly off the expected path, acknowledge that social nuance briefly before judging grammar and phrasing. '
            .'Fail only if the answer does not satisfy the requested goal, is off-topic, not Lithuanian, or unintelligible. '
            .'The learner spoke; they did not type. Never mention spelling, capitalization, casing, punctuation, writing, or typing in feedback. '
            .'Treat merged words, missing punctuation, and lower-case names in the transcript as transcription artifacts unless spoken meaning is unclear. '
            .'If the transcript has a likely speech-to-text artifact, give a natural spoken version without criticizing the learner for text formatting. '
            .'Do not correct a personal name unless the task requires a specific name; preserve the likely intended name where possible. '
            .'Score task completion by how well the spoken answer satisfies the current goal, not by exact phrase matching. '
            .'Score grammar, vocabulary, cohesion, and task completion from 0 to 100. '
            .'Use null for pronunciation unless audio-level evidence is explicitly available. '
            .'Estimate the attempt CEFR level as pre_a1, a1, a2, b1, b2, c1, or c2. '
            .'Feedback must be one short friendly English sentence about spoken meaning, pronunciation, grammar, vocabulary, or natural phrasing. '
            ."Corrected must be a natural spoken Lithuanian version of the learner's answer.";
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
