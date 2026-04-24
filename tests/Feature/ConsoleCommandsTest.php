<?php

namespace Stake\BetLookup\Tests\Feature;

use Illuminate\Console\Command;
use PHPUnit\Framework\Attributes\Test;
use Stake\BetLookup\Services\ClearanceRepository;
use Stake\BetLookup\Services\StakeApiService;
use Stake\BetLookup\Tests\TestCase;

class ConsoleCommandsTest extends TestCase
{
    // -------------------------------------------------------------------------
    // stake:check-clearance
    // -------------------------------------------------------------------------

    #[Test]
    public function check_clearance_runs_successfully_with_no_credentials(): void
    {
        $mock = \Mockery::mock(StakeApiService::class);
        $mock->shouldReceive('probe')->andReturn(0);
        $this->app->instance(StakeApiService::class, $mock);

        $this->artisan('stake:check-clearance')
            ->assertExitCode(Command::SUCCESS);
    }

    #[Test]
    public function check_clearance_shows_active_status_with_valid_credentials(): void
    {
        $repo = $this->app->make(ClearanceRepository::class);
        $repo->updateCredentials('test-cookie', 'Mozilla/5.0 Test', time() + 7200);

        $mock = \Mockery::mock(StakeApiService::class);
        $mock->shouldReceive('probe')->andReturn(200);
        $this->app->instance(StakeApiService::class, $mock);

        $this->artisan('stake:check-clearance')
            ->expectsOutputToContain('Active')
            ->assertExitCode(Command::SUCCESS);
    }

    #[Test]
    public function check_clearance_shows_maintenance_warning_when_active(): void
    {
        $repo = $this->app->make(ClearanceRepository::class);
        $repo->enableMaintenanceMode();

        $mock = \Mockery::mock(StakeApiService::class);
        $mock->shouldReceive('probe')->andReturn(403);
        $this->app->instance(StakeApiService::class, $mock);

        $this->artisan('stake:check-clearance')
            ->expectsOutputToContain('MAINTENANCE')
            ->assertExitCode(Command::SUCCESS);
    }

    #[Test]
    public function check_clearance_shows_expired_recommendation_when_invalid(): void
    {
        $repo = $this->app->make(ClearanceRepository::class);
        $repo->updateCredentials('test-cookie', 'Mozilla/5.0 Test', time() - 3600);

        $mock = \Mockery::mock(StakeApiService::class);
        $mock->shouldReceive('probe')->andReturn(403);
        $this->app->instance(StakeApiService::class, $mock);

        $this->artisan('stake:check-clearance')
            ->expectsOutputToContain('make capture')
            ->assertExitCode(Command::SUCCESS);
    }

    #[Test]
    public function check_clearance_shows_expiring_soon_recommendation(): void
    {
        $repo = $this->app->make(ClearanceRepository::class);
        $repo->updateCredentials('test-cookie', 'Mozilla/5.0 Test', time() + 1800);

        $mock = \Mockery::mock(StakeApiService::class);
        $mock->shouldReceive('probe')->andReturn(200);
        $this->app->instance(StakeApiService::class, $mock);

        $this->artisan('stake:check-clearance')
            ->expectsOutputToContain('Expiring')
            ->assertExitCode(Command::SUCCESS);
    }

    #[Test]
    public function check_clearance_shows_unexpected_probe_response(): void
    {
        $mock = \Mockery::mock(StakeApiService::class);
        $mock->shouldReceive('probe')->andReturn(429);
        $this->app->instance(StakeApiService::class, $mock);

        $this->artisan('stake:check-clearance')
            ->expectsOutputToContain('Unexpected response')
            ->assertExitCode(Command::SUCCESS);
    }

    // -------------------------------------------------------------------------
    // stake:update-clearance
    // -------------------------------------------------------------------------

    #[Test]
    public function update_clearance_succeeds_with_valid_arguments(): void
    {
        $this->artisan('stake:update-clearance', [
            'clearance'  => 'valid-clearance-cookie-value',
            'user-agent' => 'Mozilla/5.0 Test Browser',
            'expiry'     => (string) (time() + 7200),
        ])
            ->expectsOutputToContain('updated successfully')
            ->assertExitCode(Command::SUCCESS);
    }

    #[Test]
    public function update_clearance_fails_when_expiry_is_in_the_past(): void
    {
        $this->artisan('stake:update-clearance', [
            'clearance'  => 'valid-clearance-cookie-value',
            'user-agent' => 'Mozilla/5.0 Test Browser',
            'expiry'     => (string) (time() - 3600),
        ])
            ->expectsOutputToContain('must be in the future')
            ->assertExitCode(Command::FAILURE);
    }
}
