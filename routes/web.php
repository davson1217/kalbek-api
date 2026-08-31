<?php

use App\Http\Controllers\Auth\GoogleOAuthController;
use App\Http\Controllers\Cms\AuthSessionController;
use App\Http\Controllers\Cms\CharacterController;
use App\Http\Controllers\Cms\DashboardController;
use App\Http\Controllers\Cms\GoalController;
use App\Http\Controllers\Cms\NpcLineController;
use App\Http\Controllers\Cms\ScenarioController;
use App\Http\Controllers\Cms\SceneController;
use App\Http\Controllers\Cms\ScenePropController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('cms.dashboard');
});

Route::get('/login', fn () => redirect()->route('cms.login'))->name('login');

Route::prefix('cms')->name('cms.')->group(function (): void {
    Route::middleware('guest')->group(function (): void {
        Route::get('/login', [AuthSessionController::class, 'create'])->name('login');
        Route::post('/login', [AuthSessionController::class, 'store'])->name('login.store');
    });

    Route::middleware(['auth', 'cms'])->group(function (): void {
        Route::post('/logout', [AuthSessionController::class, 'destroy'])->name('logout');
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::resource('characters', CharacterController::class)->only(['index', 'store', 'update']);
        Route::resource('scenarios', ScenarioController::class)->only(['index', 'store', 'show', 'update']);
        Route::post('scenarios/{scenario}/scenes', [SceneController::class, 'store'])->name('scenarios.scenes.store');
        Route::put('scenarios/{scenario}/scenes/{scene}', [SceneController::class, 'update'])->name('scenarios.scenes.update');
        Route::delete('scenarios/{scenario}/scenes/{scene}', [SceneController::class, 'destroy'])->name('scenarios.scenes.destroy');
        Route::post('scenarios/{scenario}/scenes/{scene}/goals', [GoalController::class, 'store'])->name('scenarios.scenes.goals.store');
        Route::put('scenarios/{scenario}/scenes/{scene}/goals/{goal}', [GoalController::class, 'update'])->name('scenarios.scenes.goals.update');
        Route::delete('scenarios/{scenario}/scenes/{scene}/goals/{goal}', [GoalController::class, 'destroy'])->name('scenarios.scenes.goals.destroy');
        Route::post('scenarios/{scenario}/scenes/{scene}/lines', [NpcLineController::class, 'store'])->name('scenarios.scenes.lines.store');
        Route::put('scenarios/{scenario}/scenes/{scene}/lines/{line}', [NpcLineController::class, 'update'])->name('scenarios.scenes.lines.update');
        Route::delete('scenarios/{scenario}/scenes/{scene}/lines/{line}', [NpcLineController::class, 'destroy'])->name('scenarios.scenes.lines.destroy');
        Route::post('scenarios/{scenario}/scenes/{scene}/props', [ScenePropController::class, 'store'])->name('scenarios.scenes.props.store');
        Route::put('scenarios/{scenario}/scenes/{scene}/props/{prop}', [ScenePropController::class, 'update'])->name('scenarios.scenes.props.update');
        Route::delete('scenarios/{scenario}/scenes/{scene}/props/{prop}', [ScenePropController::class, 'destroy'])->name('scenarios.scenes.props.destroy');
    });
});

Route::get('/auth/google/redirect', [GoogleOAuthController::class, 'redirect'])
    ->name('auth.google.redirect');
Route::get('/auth/google/callback', [GoogleOAuthController::class, 'callback'])
    ->name('auth.google.callback');
