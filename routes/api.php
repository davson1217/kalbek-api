<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\LanguageController;
use App\Http\Controllers\Api\V1\LessonCompletionController;
use App\Http\Controllers\Api\V1\LessonProgressController;
use App\Http\Controllers\Api\V1\OAuthExchangeController;
use App\Http\Controllers\Api\V1\PasswordResetController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\ScenarioController;
use App\Http\Controllers\Api\V1\SpeechCheckController;
use App\Http\Controllers\Api\V1\TextToSpeechController;
use App\Modules\Subscriptions\Http\Controllers\BillingPortalController;
use App\Modules\Subscriptions\Http\Controllers\StripeWebhookController;
use App\Modules\Subscriptions\Http\Controllers\SubscriptionCheckoutController;
use App\Modules\Subscriptions\Http\Controllers\SubscriptionPlansController;
use App\Modules\Subscriptions\Http\Controllers\SubscriptionStatusController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('/health', fn () => [
        'status' => 'ok',
        'service' => config('app.name'),
    ])->name('health');

    Route::middleware('auth:sanctum')->get('/user', fn (Request $request) => $request->user())
        ->name('user');

    Route::post('/auth/register', [AuthController::class, 'register'])
        ->name('auth.register');
    Route::post('/auth/login', [AuthController::class, 'login'])
        ->name('auth.login');
    Route::post('/auth/forgot-password', [PasswordResetController::class, 'forgot'])
        ->name('auth.password.forgot');
    Route::post('/auth/reset-password', [PasswordResetController::class, 'reset'])
        ->name('auth.password.reset');
    Route::post('/auth/oauth/exchange', OAuthExchangeController::class)
        ->name('auth.oauth.exchange');

    Route::apiResource('scenarios', ScenarioController::class)
        ->only(['index', 'show']);
    Route::get('/languages', [LanguageController::class, 'index'])
        ->name('languages.index');
    Route::get('/tts', TextToSpeechController::class)
        ->name('tts');
    Route::post('/stripe/webhook', StripeWebhookController::class)
        ->name('stripe.webhook');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/auth/user', [AuthController::class, 'me'])
            ->name('auth.user');
        Route::post('/auth/logout', [AuthController::class, 'logout'])
            ->name('auth.logout');
        Route::get('/profile', [ProfileController::class, 'show'])
            ->name('profile.show');
        Route::patch('/profile', [ProfileController::class, 'update'])
            ->name('profile.update');
        Route::get('/lesson-progress', [LessonProgressController::class, 'index'])
            ->name('lesson-progress.index');
        Route::get('/subscription', SubscriptionStatusController::class)
            ->name('subscription.show');
        Route::get('/subscription/plans', SubscriptionPlansController::class)
            ->name('subscription.plans');
        Route::post('/subscription/checkout', SubscriptionCheckoutController::class)
            ->name('subscription.checkout');
        Route::post('/subscription/portal', BillingPortalController::class)
            ->name('subscription.portal');
        Route::post('/lessons/{scenario}/complete', LessonCompletionController::class)
            ->name('lessons.complete');
        Route::post('/speak-check', SpeechCheckController::class)
            ->name('speak-check');
    });
});
