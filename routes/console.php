<?php

use App\Services\Progress\RecalculateLearnerCefrLevel;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('kalbek:recalculate-cefr-levels', function (RecalculateLearnerCefrLevel $recalculator) {
    $count = $recalculator->recalculateUsersWithNewEvidence();

    $this->info("Recalculated CEFR levels for {$count} learner(s).");
})->purpose('Recalculate learner CEFR levels from recent speaking evidence');

Schedule::command('kalbek:recalculate-cefr-levels')->daily();
