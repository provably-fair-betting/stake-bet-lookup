<?php

namespace Stake\BetLookup\Tests\Unit;

use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Stake\BetLookup\Notifications\ClearanceExpiredNotification;
use Stake\BetLookup\Notifications\ClearanceExpiringNotification;
use Stake\BetLookup\Services\ClearanceAlerter;
use Stake\BetLookup\Tests\TestCase;

class ClearanceAlerterTest extends TestCase
{
    private function makeAlerter(array $overrides = []): ClearanceAlerter
    {
        return new ClearanceAlerter(array_merge([
            'clearance_alert_email'         => null,
            'clearance_alert_slack_webhook' => null,
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // alertExpired
    // -------------------------------------------------------------------------

    #[Test]
    public function alert_expired_sends_email_when_configured(): void
    {
        Notification::fake();

        $this->makeAlerter(['clearance_alert_email' => 'admin@example.com'])->alertExpired();

        Notification::assertSentOnDemand(ClearanceExpiredNotification::class);
    }

    #[Test]
    public function alert_expired_skips_email_when_not_configured(): void
    {
        Notification::fake();

        $this->makeAlerter()->alertExpired();

        Notification::assertNothingSent();
    }

    // -------------------------------------------------------------------------
    // alertExpiringSoon
    // -------------------------------------------------------------------------

    #[Test]
    public function alert_expiring_soon_sends_email_with_time_remaining(): void
    {
        Notification::fake();

        $this->makeAlerter(['clearance_alert_email' => 'admin@example.com'])
            ->alertExpiringSoon(1800);

        Notification::assertSentOnDemand(ClearanceExpiringNotification::class);
    }

    #[Test]
    public function alert_expiring_soon_handles_null_time_remaining(): void
    {
        Notification::fake();

        $this->makeAlerter(['clearance_alert_email' => 'admin@example.com'])
            ->alertExpiringSoon(null);

        Notification::assertSentOnDemand(ClearanceExpiringNotification::class);
    }

    #[Test]
    public function alert_expiring_soon_skips_email_when_not_configured(): void
    {
        Notification::fake();

        $this->makeAlerter()->alertExpiringSoon(1800);

        Notification::assertNothingSent();
    }

    #[Test]
    public function alert_expired_swallows_slack_exception_gracefully(): void
    {
        $this->makeAlerter([
            'clearance_alert_slack_webhook' => 'http://localhost:1',
        ])->alertExpired();

        $this->assertTrue(true);
    }

    #[Test]
    public function alert_expired_swallows_email_exception_gracefully(): void
    {
        Notification::shouldReceive('route')->andThrow(new \Exception('Mail failed'));

        $this->makeAlerter(['clearance_alert_email' => 'admin@example.com'])
            ->alertExpired();

        $this->assertTrue(true);
    }
}
