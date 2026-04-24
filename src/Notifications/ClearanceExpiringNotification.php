<?php

namespace Stake\BetLookup\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ClearanceExpiringNotification extends Notification
{
    public function __construct(private readonly ?int $timeRemaining)
    {
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $minutes = $this->timeRemaining !== null ? (int) ceil($this->timeRemaining / 60) : 'unknown';

        return (new MailMessage)
            ->warning()
            ->subject('Stake API Clearance Expiring Soon')
            ->line("The Cloudflare clearance cookie will expire in approximately {$minutes} minutes.")
            ->line('**Recommended Action:**')
            ->line('1. Run the capture script: `cd scripts && npm run capture`')
            ->line('2. Complete the human verification in the browser')
            ->line('3. Sync credentials to production: `npm run sync-to-production`')
            ->line('This is a proactive alert — the API is still functioning normally.');
    }
}
