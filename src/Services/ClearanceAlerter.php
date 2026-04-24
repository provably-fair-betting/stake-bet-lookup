<?php

namespace Stake\BetLookup\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Stake\BetLookup\Notifications\ClearanceExpiredNotification;
use Stake\BetLookup\Notifications\ClearanceExpiringNotification;

class ClearanceAlerter
{
    public function __construct(private readonly array $config)
    {
    }

    public function alertExpired(): void
    {
        $this->sendEmail(new ClearanceExpiredNotification());
        $this->sendSlack('🚨 *Stake API Clearance Expired*\n\nThe Cloudflare clearance has expired. Please run the capture script to renew credentials.');

        Log::error('Stake API: Clearance expired');
    }

    public function alertExpiringSoon(?int $timeRemaining): void
    {
        $minutes = $timeRemaining !== null ? (int) ceil($timeRemaining / 60) : 'unknown';

        $this->sendEmail(new ClearanceExpiringNotification($timeRemaining));
        $this->sendSlack("⚠️ *Stake API Clearance Expiring Soon*\n\nExpires in {$minutes} minutes. Renew soon to avoid service interruption.");

        Log::warning('Stake API: Clearance expiring soon', ['time_remaining' => $timeRemaining]);
    }

    private function sendEmail(object $notification): void
    {
        $email = $this->config['clearance_alert_email'] ?? null;

        if (! $email) {
            return;
        }

        try {
            Notification::route('mail', $email)->notify($notification);
        } catch (\Exception $e) {
            Log::error('Failed to send clearance notification email', ['error' => $e->getMessage()]);
        }
    }

    private function sendSlack(string $message): void
    {
        $webhook = $this->config['clearance_alert_slack_webhook'] ?? null;

        if (! $webhook) {
            return;
        }

        try {
            (new Client())->post($webhook, [
                'json' => [
                    'text'       => $message,
                    'username'   => 'Stake API Monitor',
                    'icon_emoji' => ':warning:',
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send Slack notification', ['error' => $e->getMessage()]);
        }
    }
}
