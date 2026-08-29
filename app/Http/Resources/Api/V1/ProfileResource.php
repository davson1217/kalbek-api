<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'display_name' => $this->display_name,
            'avatar_character' => $this->whenLoaded('avatarCharacter', fn () => $this->avatarCharacter ? [
                'id' => $this->avatarCharacter->slug,
                'name' => $this->avatarCharacter->name,
                'role' => $this->avatarCharacter->role,
                'image_path' => $this->avatarCharacter->image_path,
            ] : null),
            'xp' => $this->xp,
            'hearts' => $this->hearts,
            'streak' => $this->streak,
            'longest_streak' => $this->longest_streak,
            'last_practice_date' => $this->last_practice_date?->toDateString(),
        ];
    }
}
