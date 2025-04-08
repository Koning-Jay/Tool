<?php

namespace Database\Seeders;

use App\Models\Customchecks;
use Illuminate\Database\Seeder;

class CustomchecksSeeder extends Seeder
{
    public function run(): void
    {
        $checks = [
            [
                'name' => 'High CPU Usage Alert - Product Pages',
                'description' => 'Triggers when CPU usage exceeds 80% on product pages',
                'check_type' => 'cpu',
                'threshold_value' => 80,
                'comparison_operator' => '>',
                'page_pattern' => '/catalog/product/*',
                'is_active' => true,
                'alert_severity' => 'high',
                'notification_emails' => ['admin@example.com', 'tech@example.com'],
            ],
            [
                'name' => 'Memory Usage Warning - Category Pages',
                'description' => 'Monitors RAM usage on category browsing pages',
                'check_type' => 'ram',
                'threshold_value' => 75,
                'comparison_operator' => '>',
                'page_pattern' => '/catalog/category/*',
                'is_active' => true,
                'alert_severity' => 'medium',
                'notification_emails' => ['tech@example.com'],
            ],
            [
                'name' => 'Low Sales Alert - Homepage',
                'description' => 'Monitors conversion rates on homepage',
                'check_type' => 'sales',
                'threshold_value' => 100,
                'comparison_operator' => '<',
                'page_pattern' => '/',
                'is_active' => true,
                'alert_severity' => 'high',
                'notification_emails' => ['sales@example.com', 'marketing@example.com'],
            ],
            [
                'name' => 'Checkout Page Load Time',
                'description' => 'Ensures checkout pages load quickly',
                'check_type' => 'load_time',
                'threshold_value' => 3,
                'comparison_operator' => '>',
                'page_pattern' => '/checkout/*',
                'is_active' => true,
                'alert_severity' => 'critical',
                'notification_emails' => ['tech@example.com', 'ecommerce@example.com'],
            ],
            [
                'name' => 'Error Rate Monitor - All Pages',
                'description' => 'Monitors JS and PHP errors across the site',
                'check_type' => 'error_rate',
                'threshold_value' => 5,
                'comparison_operator' => '>',
                'page_pattern' => null,
                'is_active' => true, 
                'alert_severity' => 'medium',
                'notification_emails' => ['dev@example.com'],
            ],
        ];

        foreach ($checks as $check) {
            Customchecks::create($check);
        }
    }
}