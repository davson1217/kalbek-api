<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LessonProgressResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'scenario_id' => $this->whenLoaded('scenario', fn () => $this->scenario->slug),
            'completed' => $this->completed,
            'best_score' => $this->best_score,
            'xp_earned' => $this->xp_earned,
            'attempts' => $this->attempts,
            'completed_at' => $this->completed_at?->toISOString(),
        ];
    }
}
