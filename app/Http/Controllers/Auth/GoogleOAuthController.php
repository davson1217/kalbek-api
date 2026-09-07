<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\ResolveGoogleUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleOAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->stateless()->redirect();
    }

    public function callback(ResolveGoogleUser $resolveGoogleUser): RedirectResponse
    {
        $user = $resolveGoogleUser->handle(Socialite::driver('google')->stateless()->user());
        $code = Str::random(64);

        Cache::put("oauth:handoff:{$code}", $user->id, now()->addMinutes(5));

        return redirect()->away($this->frontendUrl().'/auth/callback?code='.$code);
    }

    private function frontendUrl(): string
    {
        return rtrim(config('services.kalbek.frontend_url', 'http://localhost:3000'), '/');
    }
}
