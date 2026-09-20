<?php

namespace App\Console\Commands;

use App\Services\Content\AuditScenarioContent;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('kalbek:audit-content {--fail : Return a non-zero exit code when issues are found}')]
#[Description('Audit published scenario content for risky conversation-flow issues')]
class AuditContent extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(AuditScenarioContent $auditor)
    {
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
    }
}
