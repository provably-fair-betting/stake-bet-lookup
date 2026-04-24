<?php

namespace Stake\BetLookup\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ClearanceExpiredNotification extends Notification
{
    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->error()
            ->subject('Stake API Clearance Expired')
            ->line('The Cloudflare clearance cookie for Stake API has expired.')
            ->line('The API is in maintenance mode until credentials are renewed.')
            ->line('**Action Required:**')
            ->line('1. Run the capture script: `cd scripts && npm run capture`')
            ->line('2. Complete the human verification in the browser')
            ->line('3. Sync credentials to production: `npm run sync-to-production`')
            ->line('**Estimated downtime:** ~1-2 minutes');
    }
}
