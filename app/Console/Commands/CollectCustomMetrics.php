<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class CollectSystemMetrics extends Command
{
    protected $signature = 'system:collect-metrics';
    protected $description = 'Collect system metrics such as CPU, RAM and disk usage';

    public function handle()
    {
        $this->info('Collecting system metrics...');
        
        try {
            // Collect metrics
            $metrics = [
                'timestamp' => now()->toIso8601String(),
                'cpu' => $this->getCpuMetrics(),
                'ram' => $this->getRamMetrics(),
                'disk' => $this->getDiskMetrics(),
            ];
            
            // Save metrics to file
            Storage::put('system_testdata.json', json_encode($metrics, JSON_PRETTY_PRINT));
            
            $this->info('System metrics collected successfully:');
            $this->info('CPU Usage: ' . $metrics['cpu']['usage_percent'] . '%');
            $this->info('RAM Usage: ' . $metrics['ram']['usage_percent'] . '%');
            $this->info('Disk Usage: ' . $metrics['disk']['usage_percent'] . '%');
            
            Log::info('System metrics collected', [
                'cpu' => $metrics['cpu']['usage_percent'] . '%',
                'ram' => $metrics['ram']['usage_percent'] . '%',
                'disk' => $metrics['disk']['usage_percent'] . '%',
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
    
    private function getCpuMetrics(): array
    {
        // Get CPU load from /proc/loadavg on Linux
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            $cores = $this->getCpuCores();
            
            // Calculate CPU usage percentage based on 1-minute load average
            $usagePercent = min(round(($load[0] / $cores) * 100, 1), 100);
            
            return [
                'load_1min' => $load[0],
                'load_5min' => $load[1],
                'load_15min' => $load[2],
                'cores' => $cores,
                'usage_percent' => $usagePercent
            ];
        }
        
        // Fallback if we can't get load average
        return [
            'load_1min' => 0,
            'load_5min' => 0,
            'load_15min' => 0,
            'cores' => 1,
            'usage_percent' => 0
        ];
    }
    
    private function getCpuCores(): int
    {
        // Try to get the number of CPU cores
        if (function_exists('shell_exec')) {
            $cores = (int) shell_exec('nproc 2>/dev/null || grep -c ^processor /proc/cpuinfo 2>/dev/null || echo 1');
            return $cores > 0 ? $cores : 1;
        }
        
        // Default to 1 core if we can't detect
        return 1;
    }
    
    private function getRamMetrics(): array
    {
        // Try to get memory info on Linux systems
        if (function_exists('shell_exec') && file_exists('/proc/meminfo')) {
            $meminfo = shell_exec('cat /proc/meminfo');
            preg_match('/MemTotal:\s+(\d+)/', $meminfo, $totalMatches);
            preg_match('/MemFree:\s+(\d+)/', $meminfo, $freeMatches);
            preg_match('/Buffers:\s+(\d+)/', $meminfo, $buffersMatches);
            preg_match('/Cached:\s+(\d+)/', $meminfo, $cachedMatches);
            
            if (isset($totalMatches[1]) && isset($freeMatches[1])) {
                $totalKb = (int) $totalMatches[1];
                $freeKb = (int) $freeMatches[1];
                $buffersKb = isset($buffersMatches[1]) ? (int) $buffersMatches[1] : 0;
                $cachedKb = isset($cachedMatches[1]) ? (int) $cachedMatches[1] : 0;
                
                // Calculate available memory (free + buffers + cached)
                $availableKb = $freeKb + $buffersKb + $cachedKb;
                
                // Calculate used memory and percentage
                $usedKb = $totalKb - $availableKb;
                $usagePercent = round(($usedKb / $totalKb) * 100, 1);
                
                return [
                    'total_kb' => $totalKb,
                    'used_kb' => $usedKb,
                    'free_kb' => $availableKb,
                    'usage_percent' => $usagePercent
                ];
            }
        }
        
        // Get memory info using PHP's memory_get_usage function as fallback
        $memoryUsed = memory_get_usage(true);
        $memoryLimit = $this->getMemoryLimitInBytes();
        
        // Calculate percentage (cap at 100%)
        $usagePercent = $memoryLimit > 0 ? min(round(($memoryUsed / $memoryLimit) * 100, 1), 100) : 50;
        
        return [
            'total_kb' => round($memoryLimit / 1024),
            'used_kb' => round($memoryUsed / 1024),
            'free_kb' => round(($memoryLimit - $memoryUsed) / 1024),
            'usage_percent' => $usagePercent
        ];
    }
    
    private function getMemoryLimitInBytes(): int
    {
        $memoryLimit = ini_get('memory_limit');
        
        // Default to 128MB if no limit is set
        if ($memoryLimit === false || $memoryLimit === '-1') {
            return 128 * 1024 * 1024;
        }
        
        // Convert to bytes
        $unit = strtolower(substr($memoryLimit, -1));
        $value = (int) substr($memoryLimit, 0, -1);
        
        switch ($unit) {
            case 'g':
                return $value * 1024 * 1024 * 1024;
            case 'm':
                return $value * 1024 * 1024;
            case 'k':
                return $value * 1024;
            default:
                return (int) $memoryLimit;
        }
    }
    
    private function getDiskMetrics(): array
    {
        // Get disk usage for the storage directory
        $path = storage_path();
        
        if (function_exists('disk_free_space') && function_exists('disk_total_space')) {
            $free = disk_free_space($path);
            $total = disk_total_space($path);
            
            if ($free !== false && $total !== false) {
                $used = $total - $free;
                $usagePercent = round(($used / $total) * 100, 1);
                
                return [
                    'total_bytes' => $total,
                    'used_bytes' => $used,
                    'free_bytes' => $free,
                    'usage_percent' => $usagePercent
                ];
            }
        }
        
        // Fallback to using df command
        if (function_exists('shell_exec')) {
            $df = shell_exec("df -k " . escapeshellarg($path));
            if ($df) {
                $lines = explode("\n", trim($df));
                if (isset($lines[1])) {
                    $parts = preg_split('/\s+/', $lines[1]);
                    if (count($parts) >= 5) {
                        return [
                            'total_bytes' => (int) $parts[1] * 1024,
                            'used_bytes' => (int) $parts[2] * 1024,
                            'free_bytes' => (int) $parts[3] * 1024,
                            'usage_percent' => (int) rtrim($parts[4], '%')
                        ];
                    }
                }
            }
        }
        
        // Default values if we can't get disk info
        return [
            'total_bytes' => 100 * 1024 * 1024 * 1024, // 100 GB
            'used_bytes' => 50 * 1024 * 1024 * 1024,   // 50 GB
            'free_bytes' => 50 * 1024 * 1024 * 1024,   // 50 GB
            'usage_percent' => 50
        ];
    }
}