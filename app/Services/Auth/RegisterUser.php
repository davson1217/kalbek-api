<?php

namespace App\Services\Auth;

use App\Models\Character;
use App\Models\User;
use App\Modules\Subscriptions\SubscriptionManager;
use App\Notifications\WelcomeToKalbek;
use Illuminate\Support\Facades\DB;

class RegisterUser
{
    public function __construct(private readonly SubscriptionManager $subscriptions) {}

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

            $this->subscriptions->startTrial($user);

            $user->notify(new WelcomeToKalbek);

            return $user->load('profile.avatarCharacter', 'subscriptions');
        });
    }
}
