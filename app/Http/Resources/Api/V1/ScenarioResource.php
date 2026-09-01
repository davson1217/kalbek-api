<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScenarioResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->slug,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'description' => $this->description,
            'emoji' => $this->emoji,
            'language' => $this->whenLoaded('language', fn () => [
                'code' => $this->language->code,
                'name' => $this->language->name,
                'native_name' => $this->language->native_name,
                'support_language' => [
                    'code' => $this->language->support_language_code,
                    'name' => $this->language->support_language_name,
                ],
            ]),
            'character' => $this->whenLoaded('character', fn () => [
                'id' => $this->character->slug,
                'name' => $this->character->name,
                'role' => $this->character->role,
                'image_path' => $this->character->image_path,
                'intro' => $this->character->intro,
                'praise' => $this->character->praise_lines,
                'encourage' => $this->character->encouragement_lines,
            ]),
            'tone' => $this->tone,
            'cefr_level' => $this->cefr_level?->value,
            'is_free' => (bool) $this->is_free,
            'available' => $this->getAttribute('available_for_user') ?? true,
            'availability_reason' => $this->getAttribute('availability_reason'),
            'start_scene_id' => $this->start_scene_slug,
            'scenes' => $this->whenLoaded('scenes', fn () => $this->scenes->map(fn ($scene): array => [
                'id' => $scene->slug,
                'setting' => $scene->setting,
                'cefr_level' => $scene->cefr_level?->value,
                'lines' => $scene->npcLines->map(fn ($line): array => [
                    'target_text' => $line->target_text,
                    'support_translation' => $line->support_translation,
                    'cefr_level' => $line->cefr_level?->value,
                    'trigger_goal_id' => $line->triggerGoal?->slug,
                    'priority' => $line->priority,
                ])->values(),
                'props' => $scene->props->map(fn ($prop): array => [
                    'type' => $prop->type,
                    'target_text' => $prop->target_text,
                    'support_translation' => $prop->support_translation,
                    'price' => $prop->price,
                    'metadata' => $prop->metadata,
                ])->values(),
                'goals' => $scene->goals->map(fn ($goal): array => [
                    'id' => $goal->slug,
                    'label' => $goal->label,
                    'intent' => $goal->intent,
                    'example' => $goal->example,
                    'cefr_level' => $goal->cefr_level?->value,
                    'next' => $goal->nextScene?->slug,
                ])->values(),
            ])->values()),
        ];
    }
}
