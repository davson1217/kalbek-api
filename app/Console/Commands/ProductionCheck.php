<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('kalbek:production-check {--fail : Return a non-zero exit code when blocking issues are found}')]
#[Description('Check production deployment configuration without printing secrets')]
class ProductionCheck extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $blocking = [];
        $warnings = [];
        $isProduction = config('app.env') === 'production';

        $add = function (bool $passes, string $message, bool $blocks = true) use (&$blocking, &$warnings): void {
            if ($passes) {
                return;
            }

            if ($blocks) {
                $blocking[] = $message;

                return;
            }

            $warnings[] = $message;
        };

        $isPublicHttpsUrl = fn (?string $url): bool => filled($url)
            && str_starts_with((string) $url, 'https://')
            && ! str_contains((string) $url, 'localhost')
            && ! str_contains((string) $url, '127.0.0.1');

        $appUrl = (string) config('app.url');
        $frontendUrl = (string) config('services.kalbek.frontend_url');
        $googleRedirect = (string) config('services.google.redirect');
        $aiMode = (string) config('services.kalbek.ai_mode');
        $subscription = config('subscriptions.stripe');
        $mailMailer = (string) config('mail.default');
        $mailFrom = (string) config('mail.from.address');

        $add($isProduction, 'APP_ENV should be production for a production deployment.');
        $add(! (bool) config('app.debug'), 'APP_DEBUG must be false in production.');
        $add(filled(config('app.key')), 'APP_KEY is missing. Run php artisan key:generate before deploy.');
        $add($isPublicHttpsUrl($appUrl), 'APP_URL must be a public https URL.');
        $add($isPublicHttpsUrl($frontendUrl), 'KALBEK_FRONTEND_URL must be a public https URL.');

        $add(filled(config('services.google.client_id')), 'GOOGLE_CLIENT_ID is missing.');
        $add(filled(config('services.google.client_secret')), 'GOOGLE_CLIENT_SECRET is missing.');
        $add(
            $isPublicHttpsUrl($googleRedirect) && str_starts_with($googleRedirect, rtrim($appUrl, '/').'/auth/google/callback'),
            'GOOGLE_REDIRECT_URI must match APP_URL/auth/google/callback and use https.',
        );

        $add(filled($subscription['secret'] ?? null), 'STRIPE_SECRET is missing.');
        $add(filled($subscription['webhook_secret'] ?? null), 'STRIPE_WEBHOOK_SECRET is missing.');
        $add(filled($subscription['monthly_price_id'] ?? null), 'STRIPE_MONTHLY_PRICE_ID is missing.');
        $add(filled($subscription['annual_price_id'] ?? null), 'STRIPE_ANNUAL_PRICE_ID is missing.');
        $add($isPublicHttpsUrl($subscription['success_url'] ?? null), 'STRIPE_SUCCESS_URL must be a public https URL.');
        $add($isPublicHttpsUrl($subscription['cancel_url'] ?? null), 'STRIPE_CANCEL_URL must be a public https URL.');

        $add($aiMode === 'live', 'KALBEK_AI_MODE must be live in production.');
        foreach (array_unique([
            (string) config('ai.default'),
            (string) config('ai.default_for_audio'),
            (string) config('ai.default_for_transcription'),
        ]) as $provider) {
            $add(
                filled(config("ai.providers.{$provider}.key")),
                "AI provider [{$provider}] is selected but its API key is missing.",
            );
        }

        $add(! in_array($mailMailer, ['array', 'log'], true), 'MAIL_MAILER must deliver real email in production.');
        $add($mailFrom !== '' && $mailFrom !== 'hello@example.com', 'MAIL_FROM_ADDRESS should be a verified product sender address.');
        $add(config('queue.default') !== 'sync', 'QUEUE_CONNECTION should not be sync in production.', false);
        $add(config('cache.default') === 'redis', 'CACHE_STORE should be redis for production.', false);
        $add(config('session.driver') === 'redis', 'SESSION_DRIVER should be redis for production.', false);

        if ($blocking === [] && $warnings === []) {
            $this->info('Production readiness check passed.');

            return 0;
        }

        if ($blocking !== []) {
            $this->error('Blocking production readiness issue(s):');
            foreach ($blocking as $issue) {
                $this->line("- {$issue}");
            }
        }

        if ($warnings !== []) {
            $this->warn('Production readiness warning(s):');
            foreach ($warnings as $issue) {
                $this->line("- {$issue}");
            }
        }

        return $this->option('fail') && $blocking !== [] ? 1 : 0;
    }
}
