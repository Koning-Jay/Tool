<?php

namespace App\Console\Commands;

use App\Models\Magento;
use App\Models\Check;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Filament\Resources\MagentoResource;
use Illuminate\Support\Facades\Notification as FacadesNotification;
use App\Notifications\WebsiteDownNotification;

class CheckMagentoStatus extends Command
{
    protected $signature = 'magento:check-status {id?}';
    protected $description = 'Check if Magento websites are live or down and refresh metrics';

    public function handle()
    {
        $id = $this->argument('id');
        
        if ($id) {
            $magentos = Magento::where('id', $id)->get();
        } else {
            $magentos = Magento::all();
        }
        
        foreach ($magentos as $magento) {
            // Check primary URL
            try {
                $response = Http::timeout(5)->get($magento->url);
                $primaryStatus = $response->successful() ? 'Live' : 'Down';
            } catch (\Exception $e) {
                $primaryStatus = 'Down';
            }

            Check::create([
                'magento_id' => $magento->id,
                'url_type' => 'primary',
                'status' => $primaryStatus,
                'checked_at' => now(),
            ]);

            if ($primaryStatus === 'Down' && !empty($magento->notification_emails)) {
                $this->sendNotification($magento, 'primary');
            }
            
            $this->info("Website {$magento->name} (Primary URL: {$magento->url}): {$primaryStatus}");
            
            // Check secondary URL if exists
            if (!empty($magento->secondary_url)) {
                try {
                    $response = Http::timeout(5)->get($magento->secondary_url);
                    $secondaryStatus = $response->successful() ? 'Live' : 'Down';
                } catch (\Exception $e) {
                    $secondaryStatus = 'Down';
                }

                Check::create([
                    'magento_id' => $magento->id,
                    'url_type' => 'secondary',
                    'status' => $secondaryStatus,
                    'checked_at' => now(),
                ]);

                if ($secondaryStatus === 'Down' && !empty($magento->notification_emails)) {
                    $this->sendNotification($magento, 'secondary');
                }

                $this->info("Website {$magento->name} (Secondary URL: {$magento->secondary_url}): {$secondaryStatus}");
            }
            
            // Check tertiary URL if exists
            if (!empty($magento->tertiary_url)) {
                try {
                    $response = Http::timeout(5)->get($magento->tertiary_url);
                    $tertiaryStatus = $response->successful() ? 'Live' : 'Down';
                } catch (\Exception $e) {
                    $tertiaryStatus = 'Down';
                }

                Check::create([
                    'magento_id' => $magento->id,
                    'url_type' => 'tertiary',
                    'status' => $tertiaryStatus,
                    'checked_at' => now(),
                ]);

                if ($tertiaryStatus === 'Down' && !empty($magento->notification_emails)) {
                    $this->sendNotification($magento, 'tertiary');
                }

                $this->info("Website {$magento->name} (Tertiary URL: {$magento->tertiary_url}): {$tertiaryStatus}");
            }
            
            // Check custom metrics for this domain
            $this->info("Checking custom metrics for {$magento->name}...");
            MagentoResource::checkCustomMetrics($magento);
            
            Log::info("Website status check and metrics completed for: {$magento->name}");
        }
        
        return Command::SUCCESS;
    }

    private function sendNotification(Magento $magento, string $urlType): void
    {
        try {
            Log::info("Starting notification process for {$magento->name}");
            
            $emails = $magento->notification_emails;
            if (empty($emails)) {
                Log::warning("No notification emails found for {$magento->name}");
                return;
            }

            Log::info("Found emails for notification: " . implode(', ', $emails));
            
            foreach ($emails as $email) {
                $email = trim($email);
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    try { 
                        Log::info("Sending notification to {$email}");
                        FacadesNotification::route('mail', $email)
                            ->notify(new WebsiteDownNotification($magento, $urlType));
                        $this->info("✓ Notification sent to: {$email}");
                        Log::info("Successfully sent notification to {$email}");
                    } catch (\Exception $e) {
                        Log::error("Failed to send to {$email}: " . $e->getMessage());
                        $this->error("Failed to send to {$email}: " . $e->getMessage());
                    }
                } else {
                    Log::warning("Invalid email address: {$email}");
                }
            }
        } catch (\Exception $e) {
            Log::error("Notification process failed: " . $e->getMessage());
            $this->error("Notification process failed: " . $e->getMessage());
        }
    }
}