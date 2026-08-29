<?php

namespace App\Services\Auth;

use App\Models\Character;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RegisterUser
{
    /**
     * @param  array{name: string, email: string, password: string}  $data
     */
    public function handle(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $user = User::query()->create($data);
            $character = Character::query()->where('slug', 'gabija')->first();

            $user->profile()->create([
                'avatar_character_id' => $character?->id,
                'display_name' => $data['name'],
            ]);

            return $user->load('profile.avatarCharacter');
        });
    }
}
