<?php

namespace App\Services\Auth;

use App\Models\Character;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class ResolveGoogleUser
{
    public function handle(SocialiteUser $googleUser): User
    {
        $googleId = $googleUser->getId();
        $email = $googleUser->getEmail();

        if (! $googleId || ! $email) {
            throw ValidationException::withMessages([
                'email' => ['Google did not return a usable account email.'],
            ]);
        }

        return DB::transaction(function () use ($googleUser, $googleId, $email): User {
            $user = User::query()
                ->where('google_id', $googleId)
                ->orWhere('email', $email)
                ->first();

            if (! $user) {
                $user = User::query()->create([
                    'name' => $googleUser->getName() ?: Str::before($email, '@'),
                    'email' => $email,
                    'password' => Str::random(48),
                    'email_verified_at' => now(),
                ]);
            }

            $user->forceFill([
                'google_id' => $googleId,
                'avatar_url' => $googleUser->getAvatar(),
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();

            $character = Character::query()->where('slug', 'gabija')->first();
            $user->profile()->firstOrCreate(
                [],
                [
                    'avatar_character_id' => $character?->id,
                    'display_name' => $user->name,
                ],
            );

            return $user->load('profile.avatarCharacter');
        });
    }
}
