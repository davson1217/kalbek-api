<?php

namespace App\Providers;

use App\Contracts\SpeechEvaluatorContract;
use App\Contracts\TextToSpeechSynthesizer;
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

        $this->app->bind(TextToSpeechSynthesizer::class, function () {
            return config('services.kalbek.ai_mode') === 'fake'
                ? new FakeTextToSpeechSynthesizer
                : new LaravelAiTextToSpeechSynthesizer;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
