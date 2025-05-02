<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Collect system metrics every 5 minutes
        $schedule->command('system:collect-metrics')
                ->everyFiveMinutes()
                ->appendOutputTo(storage_path('logs/metrics.log'));
                
        // Check website status every 10 minutes
        $schedule->command('magento:check-status')
                ->everyTenMinutes()
                ->appendOutputTo(storage_path('logs/website-checks.log'));
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}