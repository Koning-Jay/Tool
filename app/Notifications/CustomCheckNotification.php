<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomCheckNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $data;

    /**
     * Create a new notification instance.
     *
     * @param array $data
     * @return void
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        // Use the sync queue driver to process notifications immediately
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject("ALERT: {$this->data['check_name']} check triggered for {$this->data['domain_name']}")
            ->line("A custom check alert has been triggered for {$this->data['domain_name']}.")
            ->line("Check name: {$this->data['check_name']}")
            ->line("Check type: {$this->data['check_type']}")
            ->line("Current value: {$this->data['current_value']}")
            ->line("Threshold: {$this->data['threshold']}")
            ->line("This alert was generated on {$this->data['timestamp']}")
            ->action('View Domain', url('/admin/magentos'))
            ->line('This is an automated notification from the Wedigify monitoring system.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            'check_name' => $this->data['check_name'],
            'domain_name' => $this->data['domain_name'],
            'check_type' => $this->data['check_type'],
            'current_value' => $this->data['current_value'],
            'threshold' => $this->data['threshold'],
            'timestamp' => $this->data['timestamp'],
        ];
    }
}