<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Customchecks extends Model
{
    protected $fillable = [
        'name',
        'description',
        'check_type',    
        'threshold_value',
        'comparison_operator', 
        'is_active',
        'notification_emails',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'notification_emails' => 'array',
        'threshold_value' => 'float',
    ];

    public function magentos(): BelongsToMany
    {
        return $this->belongsToMany(Magento::class, 'customcheck_magento');
    }
    /**
     * Get available check types based on a specific health check file
     * 
     * @param string $healthCheckFile
     * @return array
     */
    public static function getAvailableCheckTypes(string $healthCheckFile = 'healthcheck.php'): array
    {
        // Standard metrics available in all health check files
        $standardTypes = [
            'cpu' => 'CPU Usage',
            'ram' => 'Memory Usage',
            'disk' => 'Disk Space'
        ];
        
        // Add custom metrics based on the health check file
        $customTypes = [];
        
        if ($healthCheckFile === 'custom_metrics_healthcheck.php') {
            $customTypes = [
                'mysql_connections' => 'MySQL Connections',
                'redis_memory' => 'Redis Memory',
                'php_processes' => 'PHP Processes',
                'average_response_time' => 'Response Time',
                'orders_per_minute' => 'Orders Per Minute', 
                'cache_hit_ratio' => 'Cache Hit Ratio'
            ];
        }
        
        // You can add additional custom metrics for other health check files here
        
        return array_merge($standardTypes, $customTypes);
    }
}