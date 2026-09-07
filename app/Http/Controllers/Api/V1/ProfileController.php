<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateProfileRequest;
use App\Http\Resources\Api\V1\ProfileResource;
use App\Models\Character;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request): ProfileResource
    {
        /** @var User $user */
        $user = $request->user();

        return ProfileResource::make($this->profileFor($user));
    }

    public function update(UpdateProfileRequest $request): ProfileResource
    {
        /** @var User $user */
        $user = $request->user();
        $profile = $this->profileFor($user);
        $data = $request->validated();

        if (array_key_exists('display_name', $data)) {
            $profile->display_name = $data['display_name'];
        }

        if (array_key_exists('avatar_character', $data)) {
            $profile->avatar_character_id = Character::query()
                ->where('slug', $data['avatar_character'])
                ->firstOrFail()
                ->id;
        }

        if (array_key_exists('strict_speech_mode', $data)) {
            $user->strict_speech_mode = $data['strict_speech_mode'];
            $user->save();
        }

        if (array_key_exists('app_language', $data)) {
            $profile->app_language = $data['app_language'];
        }

        if (array_key_exists('show_translations', $data)) {
            $profile->show_translations = $data['show_translations'];
        }

        $profile->save();

        return ProfileResource::make($profile->load('avatarCharacter', 'user.languageLevels', 'user'));
    }

    private function profileFor(User $user): Profile
    {
        return $user->profile()
            ->with('avatarCharacter')
            ->firstOrCreate(
                [],
                ['display_name' => $user->name, 'app_language' => 'en', 'show_translations' => true],
            )
            ->loadMissing('user.languageLevels');
    }
}
