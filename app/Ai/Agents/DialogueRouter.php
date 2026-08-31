<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

/* Currently unused */
class DialogueRouter implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return 'You route a Lithuanian role-play conversation using only authored CMS content. '
            .'Do not invent scene ids, goal ids, or character replies. Choose from the ids provided. '
            .'The learner may satisfy more than one goal in one utterance. If they do, skip redundant prompts and choose the most natural authored reply trigger. '
            .'next_scene_id is the scene that becomes active after the reply. reply_scene_id is the scene that supplies the immediate character reply. '
            .'If the selected goal failed, keep the learner in the current scene. '
            .'Return a concise reason for debugging.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'matched_goal_id' => $schema->string()->required(),
            'additional_goal_ids' => $schema->array()->items($schema->string())->required(),
            'next_scene_id' => $schema->string()->nullable()->required(),
            'reply_scene_id' => $schema->string()->nullable()->required(),
            'reply_trigger_goal_id' => $schema->string()->nullable()->required(),
            'should_complete' => $schema->boolean()->required(),
            'reason' => $schema->string()->required(),
        ];
    }
}
