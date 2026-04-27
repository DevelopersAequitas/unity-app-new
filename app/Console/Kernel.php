<?php

namespace App\Console;

use App\Console\Commands\LifeImpactBackfillCommand;
use App\Console\Commands\LifeImpactRecalculateUsersCommand;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        LifeImpactBackfillCommand::class,
        LifeImpactRecalculateUsersCommand::class,
    ];

    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('collaborations:expire')->dailyAt('00:10');
        $schedule->command('memberships:expire-users')->hourly();
        $schedule->command('users:expire-trial')->hourly();
    }
}
