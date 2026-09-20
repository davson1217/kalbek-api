<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;

class ScheduleKalbekTasks
{
    public function __invoke(Schedule $schedule): void
    {
        $schedule->command('kalbek:recalculate-cefr-levels')->daily();
    }
}
