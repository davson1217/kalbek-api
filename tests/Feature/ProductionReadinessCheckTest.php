<?php

namespace Tests\Feature;

use Tests\TestCase;

class ProductionReadinessCheckTest extends TestCase
{
    public function test_production_check_passes_for_complete_public_configuration(): void
    {
        $this->useProductionConfiguration();

        $this->artisan('kalbek:production-check --fail')
            ->expectsOutput('Production readiness check passed.')
            ->assertExitCode(0);
    }

    public function test_production_check_fails_for_blocking_launch_configuration_gaps(): void
    {
        config([
            'app.env' => 'local',
            'app.debug' => true,
            'app.key' => null,
            'app.url' => 'http://localhost:8080',
            'services.kalbek.frontend_url' => 'http://localhost:5174',
            'services.kalbek.ai_mode' => 'fake',
            'services.google.client_id' => null,
            'services.google.client_secret' => null,
            'services.google.redirect' => 'http://localhost:8080/auth/google/callback',
            'subscriptions.stripe.secret' => null,
            'subscriptions.stripe.webhook_secret' => null,
            'subscriptions.stripe.monthly_price_id' => null,
            'subscriptions.stripe.annual_price_id' => null,
            'subscriptions.stripe.success_url' => 'http://localhost:5174/profile',
            'subscriptions.stripe.cancel_url' => 'http://localhost:5174/profile',
            'ai.default' => 'openrouter',
            'ai.default_for_audio' => 'openrouter',
            'ai.default_for_transcription' => 'openrouter',
            'ai.providers.openrouter.key' => null,
            'mail.default' => 'log',
            'mail.from.address' => 'hello@example.com',
        ]);

        $this->artisan('kalbek:production-check --fail')
            ->expectsOutput('Blocking production readiness issue(s):')
            ->expectsOutputToContain('APP_ENV should be production')
            ->expectsOutputToContain('STRIPE_WEBHOOK_SECRET is missing')
            ->expectsOutputToContain('KALBEK_AI_MODE must be live')
            ->assertExitCode(1);
    }

    private function useProductionConfiguration(): void
    {
        config([
            'app.env' => 'production',
            'app.debug' => false,
            'app.key' => 'base64:test-key',
            'app.url' => 'https://api.kalbek.test',
            'services.kalbek.frontend_url' => 'https://app.kalbek.test',
            'services.kalbek.ai_mode' => 'live',
            'services.google.client_id' => 'google-client-id',
            'services.google.client_secret' => 'google-client-secret',
            'services.google.redirect' => 'https://api.kalbek.test/auth/google/callback',
            'subscriptions.stripe.secret' => 'sk_live_test',
            'subscriptions.stripe.webhook_secret' => 'whsec_test',
            'subscriptions.stripe.monthly_price_id' => 'price_monthly',
            'subscriptions.stripe.annual_price_id' => 'price_annual',
            'subscriptions.stripe.success_url' => 'https://app.kalbek.test/profile',
            'subscriptions.stripe.cancel_url' => 'https://app.kalbek.test/profile',
            'ai.default' => 'openrouter',
            'ai.default_for_audio' => 'openrouter',
            'ai.default_for_transcription' => 'openrouter',
            'ai.providers.openrouter.key' => 'openrouter-key',
            'mail.default' => 'smtp',
            'mail.from.address' => 'hello@kalbek.test',
            'queue.default' => 'redis',
            'cache.default' => 'redis',
            'session.driver' => 'redis',
        ]);
    }
}
