<?php

use App\Models\User;
use App\Services\Content\AuditScenarioContent;
use App\Services\Progress\RecalculateLearnerCefrLevel;
use App\UserRole;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('kalbek:recalculate-cefr-levels', function (RecalculateLearnerCefrLevel $recalculator) {
    $count = $recalculator->recalculateUsersWithNewEvidence();

    $this->info("Recalculated CEFR levels for {$count} learner(s).");
})->purpose('Recalculate learner CEFR levels from recent speaking evidence');

Schedule::command('kalbek:recalculate-cefr-levels')->daily();

Artisan::command('kalbek:audit-content {--fail : Return a non-zero exit code when issues are found}', function (AuditScenarioContent $auditor) {
    $issues = $auditor->handle();

    if ($issues === []) {
        $this->info('Scenario content audit passed.');

        return 0;
    }

    $this->warn('Scenario content audit found '.count($issues).' issue(s):');

    foreach ($issues as $issue) {
        $this->line("- {$issue}");
    }

    return $this->option('fail') ? 1 : 0;
})->purpose('Audit published scenario content for risky conversation-flow issues');

Artisan::command('kalbek:production-check {--fail : Return a non-zero exit code when blocking issues are found}', function () {
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
})->purpose('Check production deployment configuration without printing secrets');

Artisan::command('kalbek:grant-cms-access {email} {--role=admin}', function () {
    $role = UserRole::from((string) $this->option('role'));

    if (! $role->canAccessCms()) {
        $this->error('Role must be admin or teacher.');

        return 1;
    }

    $user = User::query()->where('email', $this->argument('email'))->firstOrFail();
    $user->update(['role' => $role]);

    $this->info("Granted {$role->value} CMS access to {$user->email}.");

    return 0;
})->purpose('Grant CMS access to an existing user');

Artisan::command('kalbek:create-cms-user {email} {--name=} {--role=admin} {--password=}', function () {
    $role = UserRole::from((string) $this->option('role'));

    if (! $role->canAccessCms()) {
        $this->error('Role must be admin or teacher.');

        return 1;
    }

    $password = (string) ($this->option('password') ?: Str::password(16));
    $user = User::query()->updateOrCreate(
        ['email' => $this->argument('email')],
        [
            'name' => $this->option('name') ?: $this->argument('email'),
            'password' => Hash::make($password),
            'role' => $role,
            'email_verified_at' => now(),
        ],
    );

    $this->info("CMS {$role->value} user ready: {$user->email}");

    if (! $this->option('password')) {
        $this->warn("Generated password: {$password}");
    }

    return 0;
})->purpose('Create or update a CMS user with password access');
