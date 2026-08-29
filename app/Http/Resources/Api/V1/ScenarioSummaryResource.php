<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScenarioSummaryResource extends JsonResource
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
            ]),
            'tone' => $this->tone,
            'cefr_level' => $this->cefr_level?->value,
            'available' => true,
        ];
    }
}
