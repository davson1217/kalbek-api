<?php

namespace App\Providers;

use App\Contracts\DialogueOrchestratorContract;
use App\Contracts\SpeechEvaluatorContract;
use App\Contracts\TextToSpeechSynthesizer;
use App\Modules\Subscriptions\Contracts\SubscriptionGateway;
use App\Modules\Subscriptions\Gateways\StripeSubscriptionGateway;
use App\Services\Ai\DialogueOrchestrator;
use App\Services\Ai\FakeDialogueOrchestrator;
use App\Services\Ai\FakeSpeechEvaluator;
use App\Services\Ai\FakeTextToSpeechSynthesizer;
use App\Services\Ai\LaravelAiTextToSpeechSynthesizer;
use App\Services\Ai\SpeechEvaluator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SpeechEvaluatorContract::class, function () {
            return config('services.kalbek.ai_mode') === 'fake'
                ? new FakeSpeechEvaluator
                : new SpeechEvaluator;
        });

        $this->app->bind(DialogueOrchestratorContract::class, function () {
            return config('services.kalbek.ai_mode') === 'fake'
                ? new FakeDialogueOrchestrator
                : new DialogueOrchestrator;
        });

        $this->app->bind(TextToSpeechSynthesizer::class, function () {
            return config('services.kalbek.ai_mode') === 'fake'
                ? new FakeTextToSpeechSynthesizer
                : new LaravelAiTextToSpeechSynthesizer;
        });

        $this->app->bind(SubscriptionGateway::class, StripeSubscriptionGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
