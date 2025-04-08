<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule)
    {
        // Check website status every 5 minutes
        $schedule->command('magento:check-status --queue')
                 ->everyFiveMinutes()
                 ->withoutOverlapping();
        
        // Make sure to also schedule the queue worker to process jobs
        $schedule->command('queue:work --stop-when-empty --queue=website-checks')
                 ->everyMinute()
                 ->withoutOverlapping();
    }

    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}