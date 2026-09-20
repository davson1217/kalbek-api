<?php

namespace App\Console\Commands;

use App\Services\Progress\RecalculateLearnerCefrLevel;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('kalbek:recalculate-cefr-levels')]
#[Description('Recalculate learner CEFR levels from recent speaking evidence')]
class RecalculateCefrLevels extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(RecalculateLearnerCefrLevel $recalculator)
    {
        $count = $recalculator->recalculateUsersWithNewEvidence();

        $this->info("Recalculated CEFR levels for {$count} learner(s).");
    }
}
