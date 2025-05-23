<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Croustibat\FilamentJobsMonitor\Traits\QueueProgress;

class CollectSystemMetricsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, QueueProgress;
    
    public $timeout = 300; // 3 minutes
    public $tries = 3;     

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->onQueue('system-metrics');
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Log::info('Starting system metrics collection job');

        try {
            $output = Artisan::call('system:collect-metrics');

            // Get the output of the command
            $outputText = Artisan::output();
            Log::info('System metrics collection completed', [
                'exit_code' => $output,
                'output' => $outputText
            ]);

            // Append to log file
            file_put_contents(
                storage_path('logs/metrics.log'), 
                '[' . date('Y-m-d H:i:s') . '] ' . $outputText . PHP_EOL, 
                FILE_APPEND
            );
        } catch (\Exception $e) {
            Log::error('System metrics collection failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}