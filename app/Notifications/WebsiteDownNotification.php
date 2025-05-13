<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class WebsiteDownNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $magento;
    protected $urlType;

    public function __construct($magento, $urlType)
    {
        $this->magento = $magento;
        $this->urlType = $urlType;
    }

    public function via($notifiable)
    {
        // Use the sync queue driver to process notifications immediately
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        try {
            $url = match ($this->urlType) {
                'primary' => $this->magento->url,
                'secondary' => $this->magento->secondary_url,
                'tertiary' => $this->magento->tertiary_url,
                default => throw new \Exception("Invalid URL type: {$this->urlType}")
            };

            return (new MailMessage)
                ->error()
                ->subject('⚠️ Website Down Alert: ' . $this->magento->name)
                ->greeting('Alert: Website Down')
                ->line("The {$this->urlType} URL for {$this->magento->name} is currently down.")
                ->line("URL affected: {$url}")
                ->line("Time detected: " . now()->format('Y-m-d H:i:s'))
                ->line('Our system will continue monitoring the website.');
        } catch (\Exception $e) {
            Log::error("Failed to create mail message: " . $e->getMessage());
            throw $e;
        }
    }
}
