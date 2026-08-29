<?php

use App\Http\Controllers\Auth\GoogleOAuthController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect()->route('cms.dashboard');
});

Route::prefix('cms')->name('cms.')->group(function (): void {
    Route::get('/', fn () => Inertia::render('Dashboard'))->name('dashboard');
});

Route::get('/auth/google/redirect', [GoogleOAuthController::class, 'redirect'])
    ->name('auth.google.redirect');
Route::get('/auth/google/callback', [GoogleOAuthController::class, 'callback'])
    ->name('auth.google.callback');
