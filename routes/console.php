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
