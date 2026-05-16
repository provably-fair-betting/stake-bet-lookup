<?php

namespace Stake\BetLookup\Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Stake\BetLookup\Services\ClearanceRepository;
use Stake\BetLookup\Tests\TestCase;

class ClearanceRepositoryTest extends TestCase
{
    use RefreshDatabase;

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
    }

    private function makeRepo(array $overrides = []): ClearanceRepository
    {
        return new ClearanceRepository(array_merge([
            'clearance_warning_threshold' => 3600,
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // getCredentials
    // -------------------------------------------------------------------------

    #[Test]
    public function it_returns_nulls_when_nothing_is_stored(): void
    {
        $credentials = $this->makeRepo()->getCredentials();

        $this->assertNull($credentials['clearance_cookie']);
        $this->assertNull($credentials['user_agent']);
        $this->assertNull($credentials['expiry']);
    }

    #[Test]
    public function it_reads_credentials_from_cache_after_update(): void
    {
        $repo = $this->makeRepo();
        $repo->updateCredentials('cached-cookie', 'cached-agent', time() + 3600);

        $credentials = $repo->getCredentials();

        $this->assertEquals('cached-cookie', $credentials['clearance_cookie']);
        $this->assertEquals('cached-agent', $credentials['user_agent']);
    }

    #[Test]
    public function it_reads_credentials_from_database_when_cache_is_empty(): void
    {
        $repo = $this->makeRepo();
        $repo->updateCredentials('db-cookie', 'db-agent', time() + 7200);

        \Illuminate\Support\Facades\Cache::flush();

        $credentials = $repo->getCredentials();

        $this->assertEquals('db-cookie', $credentials['clearance_cookie']);
        $this->assertEquals('db-agent', $credentials['user_agent']);
    }

    // -------------------------------------------------------------------------
    // updateCredentials
    // -------------------------------------------------------------------------

    #[Test]
    public function it_persists_credentials_to_database_on_update(): void
    {
        $repo = $this->makeRepo();
        $repo->updateCredentials('db-cookie', 'db-agent', time() + 7200, '127.0.0.1');

        $this->assertDatabaseHas('stake_clearance', [
            'clearance_cookie' => 'db-cookie',
            'user_agent'       => 'db-agent',
        ]);
    }

    #[Test]
    public function it_disables_maintenance_mode_on_credential_update(): void
    {
        $repo = $this->makeRepo();
        $repo->enableMaintenanceMode();
        $repo->updateCredentials('cookie', 'agent', time() + 7200);

        $this->assertFalse($repo->isInMaintenanceMode());
    }

    // -------------------------------------------------------------------------
    // isValid
    // -------------------------------------------------------------------------

    #[Test]
    public function it_is_invalid_when_no_credentials_set(): void
    {
        $this->assertFalse($this->makeRepo()->isValid());
    }

    #[Test]
    public function it_is_valid_when_expiry_is_in_the_future(): void
    {
        $repo = $this->makeRepo();
        $repo->updateCredentials('cookie', 'agent', time() + 7200);
        $this->assertTrue($repo->isValid());
    }

    #[Test]
    public function it_is_invalid_when_expiry_is_in_the_past(): void
    {
        $repo = $this->makeRepo();
        $repo->updateCredentials('cookie', 'agent', time() - 1);
        $this->assertFalse($repo->isValid());
    }

    // -------------------------------------------------------------------------
    // isExpiringSoon
    // -------------------------------------------------------------------------

    #[Test]
    public function it_is_not_expiring_soon_when_no_expiry_set(): void
    {
        $this->assertFalse($this->makeRepo()->isExpiringSoon());
    }

    #[Test]
    public function it_is_expiring_soon_when_within_threshold(): void
    {
        $repo = $this->makeRepo(['clearance_warning_threshold' => 3600]);
        $repo->updateCredentials('cookie', 'agent', time() + 1800);
        $this->assertTrue($repo->isExpiringSoon());
    }

    #[Test]
    public function it_is_not_expiring_soon_when_beyond_threshold(): void
    {
        $repo = $this->makeRepo(['clearance_warning_threshold' => 3600]);
        $repo->updateCredentials('cookie', 'agent', time() + 7200);
        $this->assertFalse($repo->isExpiringSoon());
    }

    // -------------------------------------------------------------------------
    // getTimeUntilExpiry
    // -------------------------------------------------------------------------

    #[Test]
    public function it_returns_null_time_until_expiry_when_not_set(): void
    {
        $this->assertNull($this->makeRepo()->getTimeUntilExpiry());
    }

    #[Test]
    public function it_returns_approximate_time_until_expiry(): void
    {
        $repo = $this->makeRepo();
        $repo->updateCredentials('cookie', 'agent', time() + 3600);
        $remaining = $repo->getTimeUntilExpiry();

        $this->assertGreaterThan(3595, $remaining);
        $this->assertLessThanOrEqual(3600, $remaining);
    }

    // -------------------------------------------------------------------------
    // getExpiryStatus
    // -------------------------------------------------------------------------

    #[Test]
    public function it_returns_expired_status_when_past_expiry(): void
    {
        $repo = $this->makeRepo();
        $repo->updateCredentials('cookie', 'agent', time() - 1);
        $this->assertEquals('Expired', $repo->getExpiryStatus());
    }

    #[Test]
    public function it_returns_expiring_soon_status_within_threshold(): void
    {
        $repo = $this->makeRepo(['clearance_warning_threshold' => 3600]);
        $repo->updateCredentials('cookie', 'agent', time() + 1800);
        $this->assertStringContainsString('Expiring soon', $repo->getExpiryStatus());
    }

    #[Test]
    public function it_returns_active_status_with_time_remaining(): void
    {
        $repo = $this->makeRepo();
        $repo->updateCredentials('cookie', 'agent', time() + 7200);
        $this->assertStringContainsString('Active', $repo->getExpiryStatus());
    }

    // -------------------------------------------------------------------------
    // enableMaintenanceMode / disableMaintenanceMode / isInMaintenanceMode
    // -------------------------------------------------------------------------

    #[Test]
    public function it_is_not_in_maintenance_mode_by_default(): void
    {
        $this->assertFalse($this->makeRepo()->isInMaintenanceMode());
    }

    #[Test]
    public function it_enters_maintenance_mode_when_enabled(): void
    {
        $repo = $this->makeRepo();
        $repo->enableMaintenanceMode();

        $this->assertTrue($repo->isInMaintenanceMode());
    }

    #[Test]
    public function it_exits_maintenance_mode_when_disabled(): void
    {
        $repo = $this->makeRepo();
        $repo->enableMaintenanceMode();
        $repo->disableMaintenanceMode();

        $this->assertFalse($repo->isInMaintenanceMode());
    }

    // -------------------------------------------------------------------------
    // markAlertSent / wasAlertSent
    // -------------------------------------------------------------------------

    #[Test]
    public function it_has_not_sent_alert_by_default(): void
    {
        $this->assertFalse($this->makeRepo()->wasAlertSent());
    }

    #[Test]
    public function it_records_when_alert_is_sent(): void
    {
        $repo = $this->makeRepo();
        $repo->markAlertSent();

        $this->assertTrue($repo->wasAlertSent());
    }

    #[Test]
    public function it_clears_alert_flag_when_maintenance_mode_is_disabled(): void
    {
        $repo = $this->makeRepo();
        $repo->markAlertSent();
        $repo->disableMaintenanceMode();

        $this->assertFalse($repo->wasAlertSent());
    }
}
