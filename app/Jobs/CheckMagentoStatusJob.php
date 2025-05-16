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

class CheckMagentoStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, QueueProgress;

    protected $magentoId;
    public $timeout = 300; // 5 minutes
    public $tries = 3;

    /**
     * Create a new job instance.
     *
     * @param int|null $magentoId
     * @return void
     */
    public function __construct($magentoId = null)
    {
        $this->magentoId = $magentoId;
        $this->onQueue('website-checks');
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Log::info('Starting Magento status check job', ['magento_id' => $this->magentoId]);
        
        try {
            $this->setProgress(0, 100);
            
            if ($this->magentoId) {
                $output = Artisan::call('magento:check-status', ['id' => $this->magentoId]);
            } else {
                $output = Artisan::call('magento:check-status');
            }
            
            $this->setProgress(50, 100);

            $outputText = Artisan::output();
            Log::info('Magento status check completed', [
                'exit_code' => $output,
                'output' => $outputText
            ]);
            
            file_put_contents(
                storage_path('logs/website-checks.log'),
                '[' . date('Y-m-d H:i:s') . '] ' . $outputText . PHP_EOL,
                FILE_APPEND
            );
            
            $this->setProgress(100, 100);
        } catch (\Exception $e) {
            Log::error('Magento status check failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }
}