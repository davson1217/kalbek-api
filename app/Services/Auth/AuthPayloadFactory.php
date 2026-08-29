<?php

namespace App\Services\Auth;

use App\Http\Resources\Api\V1\ProfileResource;
use App\Models\User;

class AuthPayloadFactory
{
    /**
     * @return array{token: string, user: array{id: int, name: string, email: string, profile: array<string, mixed>|null}}
     */
    public function make(User $user): array
    {
        return [
            'token' => $user->createToken('learner')->plainTextToken,
            'user' => $this->user($user),
        ];
    }

    /**
     * @return array{id: int, name: string, email: string, profile: array<string, mixed>|null}
     */
    public function user(User $user): array
    {
        $user->loadMissing('profile.avatarCharacter');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'profile' => $user->profile ? ProfileResource::make($user->profile)->resolve() : null,
        ];
    }
}
