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
            'available' => true,
            'start_scene_id' => $this->start_scene_slug,
            'scenes' => $this->whenLoaded('scenes', fn () => $this->scenes->map(fn ($scene): array => [
                'id' => $scene->slug,
                'setting' => $scene->setting,
                'lines' => $scene->npcLines->map(fn ($line): array => [
                    'lt' => $line->lt,
                    'en' => $line->en,
                ])->values(),
                'props' => $scene->props->map(fn ($prop): array => [
                    'type' => $prop->type,
                    'lt' => $prop->lt,
                    'en' => $prop->en,
                    'price' => $prop->price,
                    'metadata' => $prop->metadata,
                ])->values(),
                'goals' => $scene->goals->map(fn ($goal): array => [
                    'id' => $goal->slug,
                    'label' => $goal->label,
                    'intent' => $goal->intent,
                    'example' => $goal->example,
                    'next' => $goal->nextScene?->slug,
                ])->values(),
            ])->values()),
        ];
    }
}
