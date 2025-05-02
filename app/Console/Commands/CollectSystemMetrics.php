<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class CollectSystemMetrics extends Command
{
    protected $signature = 'system:collect-metrics';
    protected $description = 'Collect system metrics from test data file';
    
    // Define the exact file path
    protected $testDataFilePath = 'C:\xampp\htdocs\Monitoring-tool\Tool\storage\app\system_testdata.json';

    public function handle()
    {
        $this->info('Collecting system metrics...');
        
        try {
            // Check if test data file exists
            if (!file_exists($this->testDataFilePath)) {
                $this->error('Test data file does not exist: ' . $this->testDataFilePath);
                return Command::FAILURE;
            }
            
            $this->info('Reading from test data file: ' . $this->testDataFilePath);
            
            // Read metrics directly from file to avoid caching issues
            $metricsJson = file_get_contents($this->testDataFilePath);
            $metrics = json_decode($metricsJson, true);
            
            // Check if JSON could be parsed
            if (!is_array($metrics)) {
                $this->error('Could not parse test data file. Invalid JSON format.');
                return Command::FAILURE;
            }
            
            // Output file modification time to verify we're reading the latest version
            $modTime = date('Y-m-d H:i:s', filemtime($this->testDataFilePath));
            $this->info('File last modified: ' . $modTime);
            
            // Display the metrics
            $this->displayMetrics($metrics);
            
            // Log the metrics with calculated usage percentages
            Log::info('System metrics collected from test data', [
                'cpu' => $this->calculateUsagePercent($metrics['cpu']['used_gb'] ?? 0, $metrics['cpu']['total_gb'] ?? 0),
                'ram' => $this->calculateUsagePercent($metrics['ram']['used_mb'] ?? 0, $metrics['ram']['total_mb'] ?? 0),
                'disk' => $this->calculateUsagePercent($metrics['disk']['used_gb'] ?? 0, $metrics['disk']['total_gb'] ?? 0),
            ]);
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to collect system metrics: ' . $e->getMessage());
            Log::error('Failed to collect system metrics', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return Command::FAILURE;
        }
    }
    
    /**
     * Calculate usage percentage
     * 
     * @param float $used
     * @param float $total
     * @return float
     */
    protected function calculateUsagePercent($used, $total)
    {
        if ($total <= 0) {
            return 0;
        }
        
        return round(($used / $total) * 100, 2);
    }
    
    /**
     * Display metrics in console
     * 
     * @param array $metrics
     * @return void
     */
    protected function displayMetrics($metrics)
    {
        $this->info('System metrics from test data file:');
        
        // CPU metrics
        if (isset($metrics['cpu'])) {
            $cpuData = $metrics['cpu'];
            $cpuUsed = isset($cpuData['used_gb']) ? (float)$cpuData['used_gb'] : null;
            $cpuTotal = isset($cpuData['total_gb']) ? (float)$cpuData['total_gb'] : null;
            
            // Calculate usage percentage instead of using the one from the JSON
            $cpuUsagePercent = ($cpuTotal > 0 && $cpuUsed !== null) ? 
                $this->calculateUsagePercent($cpuUsed, $cpuTotal) : null;
            
            $cpuUsageText = $cpuUsagePercent !== null ? $cpuUsagePercent . '%' : 'N/A';
            $cpuUsedText = $cpuUsed !== null ? $cpuUsed . 'GB' : 'N/A';
            $cpuTotalText = $cpuTotal !== null ? $cpuTotal . 'GB' : 'N/A';
            
            $this->info("CPU Usage: $cpuUsageText ($cpuUsedText / $cpuTotalText)");
        } else {
            $this->warn('CPU metrics not found in test data file');
        }
        
        // RAM metrics
        if (isset($metrics['ram'])) {
            $ramData = $metrics['ram'];
            $ramUsed = isset($ramData['used_mb']) ? (float)$ramData['used_mb'] : null;
            $ramTotal = isset($ramData['total_mb']) ? (float)$ramData['total_mb'] : null;
            
            // Calculate usage percentage instead of using the one from the JSON
            $ramUsagePercent = ($ramTotal > 0 && $ramUsed !== null) ? 
                $this->calculateUsagePercent($ramUsed, $ramTotal) : null;
            
            $ramUsageText = $ramUsagePercent !== null ? $ramUsagePercent . '%' : 'N/A';
            $ramUsedText = $ramUsed !== null ? $ramUsed . 'MB' : 'N/A';
            $ramTotalText = $ramTotal !== null ? $ramTotal . 'MB' : 'N/A';
            
            $this->info("RAM Usage: $ramUsageText ($ramUsedText / $ramTotalText)");
        } else {
            $this->warn('RAM metrics not found in test data file');
        }
        
        // Disk metrics
        if (isset($metrics['disk'])) {
            $diskData = $metrics['disk'];
            $diskUsed = isset($diskData['used_gb']) ? (float)$diskData['used_gb'] : null;
            $diskTotal = isset($diskData['total_gb']) ? (float)$diskData['total_gb'] : null;
            $mountPoint = isset($diskData['mount_point']) ? $diskData['mount_point'] : 'N/A';
            
            $diskUsagePercent = ($diskTotal > 0 && $diskUsed !== null) ? 
                $this->calculateUsagePercent($diskUsed, $diskTotal) : null;
            
            $diskUsageText = $diskUsagePercent !== null ? $diskUsagePercent . '%' : 'N/A';
            $diskUsedText = $diskUsed !== null ? $diskUsed . 'GB' : 'N/A';
            $diskTotalText = $diskTotal !== null ? $diskTotal . 'GB' : 'N/A';
            
            $this->info("Disk Usage: $diskUsageText ($diskUsedText / $diskTotalText)");
        } else {
            $this->warn('Disk metrics not found in test data file');
        }
        
        // Display any additional metrics that may be in the file
        foreach ($metrics as $key => $value) {
            if (!in_array($key, ['cpu', 'ram', 'disk']) && is_array($value)) {
                $this->info("$key: " . json_encode($value));
            }
        }
    }
}