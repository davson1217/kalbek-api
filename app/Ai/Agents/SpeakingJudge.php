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
        return "You judge a learner's spoken target language in a role-play conversation. "
            .'Decide by communicative intent: the learner may answer freely and does not need to match the model phrase. '
            .'If their answer satisfies the current goal in the situation, pass it even when it includes extra natural information. '
            .'If their answer is understandable but indirect, over-expanded, or slightly off the expected path, acknowledge that social nuance briefly before judging grammar and phrasing. '
            .'Fail only if the answer does not satisfy the requested goal, is off-topic, not in the target language, or unintelligible. '
            .'The learner spoke; they did not type. Never mention spelling, capitalization, casing, punctuation, writing, or typing in feedback. '
            .'Treat merged words, missing punctuation, and lower-case names in the transcript as transcription artifacts unless spoken meaning is unclear. '
            .'If the transcript has a likely speech-to-text artifact, give a natural spoken version without criticizing the learner for text formatting. '
            .'Do not correct a personal name unless the task requires a specific name; preserve the likely intended name where possible. '
            .'Score task completion by how well the spoken answer satisfies the current goal, not by exact phrase matching. '
            .'The prompt includes a content CEFR level; judge grammar, vocabulary range, cohesion, and task completion relative to that level. '
            .'Use the learner CEFR level only to phrase feedback helpfully, never to excuse an answer that misses the current content goal. '
            .'Strict mode means less tolerance within the same CEFR level; it does not mean applying B-level expectations to A-level content. '
            .'Score grammar, vocabulary, cohesion, and task completion from 0 to 100. '
            .'Use null for pronunciation unless audio-level evidence is explicitly available. '
            .'Estimate the attempt CEFR level as pre_a1, a1, a2, b1, b2, c1, or c2. '
            .'Feedback must be one short friendly English sentence about spoken meaning, pronunciation, grammar, vocabulary, or natural phrasing. '
            ."Corrected must be a natural spoken target-language version of the learner's answer. "
            .'Set intent_match to full when the goal is clearly answered, partial when the answer is related but incomplete, and off_topic when it misses the goal. '
            .'Set went_off_script to true when the learner adds extra information or answers in an unexpected but still conversationally acceptable way. '
            .'Use communication_note to explain the communicative result in one short English sentence. '
            .'Use improvement_focus for the single most useful next focus area.';
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
            'intent_match' => $schema->string()->enum(['full', 'partial', 'off_topic'])->required(),
            'understood_meaning' => $schema->boolean()->required(),
            'went_off_script' => $schema->boolean()->required(),
            'communication_note' => $schema->string()->required(),
            'improvement_focus' => $schema->string()->enum(['grammar', 'vocabulary', 'pronunciation', 'coherence', 'task', 'none'])->required(),
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
