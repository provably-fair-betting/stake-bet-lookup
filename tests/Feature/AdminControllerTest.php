<?php

namespace Stake\BetLookup\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Stake\BetLookup\Services\ClearanceRepository;
use Stake\BetLookup\Services\StakeApiService;
use Stake\BetLookup\Tests\TestCase;

class AdminControllerTest extends TestCase
{
    private function auth(): array
    {
        return ['Authorization' => 'Bearer ' . $this->adminToken()];
    }

    private function seedCredentials(): void
    {
        $repo = $this->app->make(ClearanceRepository::class);
        $repo->updateCredentials('test-clearance-cookie', 'Mozilla/5.0 Test', time() + 7200);
    }

    // -------------------------------------------------------------------------
    // AuthenticateAdmin middleware
    // -------------------------------------------------------------------------

    #[Test]
    public function it_rejects_request_with_no_token(): void
    {
        $this->getJson('/api/admin/clearance-status')
            ->assertStatus(401)
            ->assertJson(['success' => false]);
    }

    #[Test]
    public function it_rejects_request_with_invalid_token(): void
    {
        $this->getJson('/api/admin/clearance-status', ['Authorization' => 'Bearer wrong-token'])
            ->assertStatus(401)
            ->assertJson(['success' => false]);
    }

    #[Test]
    public function it_allows_request_with_valid_token(): void
    {
        $this->getJson('/api/admin/clearance-status', $this->auth())
            ->assertStatus(200);
    }

    #[Test]
    public function it_returns_500_when_admin_token_not_configured(): void
    {
        $this->app['config']->set('bet-lookup.admin_token', null);

        $this->getJson('/api/admin/clearance-status', ['Authorization' => 'Bearer any-token'])
            ->assertStatus(500)
            ->assertJson(['success' => false]);
    }

    // -------------------------------------------------------------------------
    // GET /api/admin/clearance-status
    // -------------------------------------------------------------------------

    #[Test]
    public function clearance_status_returns_full_status_shape(): void
    {
        $this->getJson('/api/admin/clearance-status', $this->auth())
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'is_valid',
                    'is_expiring_soon',
                    'status',
                    'time_until_expiry',
                    'expires_at',
                    'clearance_cookie_set',
                    'user_agent_set',
                    'maintenance_mode',
                ],
            ]);
    }

    // -------------------------------------------------------------------------
    // GET /api/admin/clearance-credentials
    // -------------------------------------------------------------------------

    #[Test]
    public function clearance_credentials_returns_404_when_not_configured(): void
    {
        $this->getJson('/api/admin/clearance-credentials', $this->auth())
            ->assertStatus(404)
            ->assertJson(['success' => false]);
    }

    #[Test]
    public function clearance_credentials_returns_credentials_when_set(): void
    {
        $this->seedCredentials();

        $this->getJson('/api/admin/clearance-credentials', $this->auth())
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => ['clearance_cookie', 'user_agent', 'expires_at'],
            ])
            ->assertJson(['data' => ['clearance_cookie' => 'test-clearance-cookie']]);
    }

    // -------------------------------------------------------------------------
    // POST /api/admin/update-clearance
    // -------------------------------------------------------------------------

    #[Test]
    public function update_clearance_accepts_valid_credentials(): void
    {
        $mock = \Mockery::mock(StakeApiService::class);
        $mock->shouldReceive('probeWith')->andReturn(200);
        $this->app->instance(StakeApiService::class, $mock);

        $this->postJson('/api/admin/update-clearance', [
            'clearance' => 'new-clearance-cookie-value',
            'userAgent' => 'Mozilla/5.0 Test Browser',
            'expiry'    => time() + 7200,
        ], $this->auth())
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    #[Test]
    public function update_clearance_rejects_credentials_when_probe_fails(): void
    {
        $mock = \Mockery::mock(StakeApiService::class);
        $mock->shouldReceive('probeWith')->andReturn(403);
        $this->app->instance(StakeApiService::class, $mock);

        $this->postJson('/api/admin/update-clearance', [
            'clearance' => 'new-clearance-cookie-value',
            'userAgent' => 'Mozilla/5.0 Test Browser',
            'expiry'    => time() + 7200,
        ], $this->auth())
            ->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    #[Test]
    public function update_clearance_rejects_missing_fields(): void
    {
        $this->postJson('/api/admin/update-clearance', [], $this->auth())
            ->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    #[Test]
    public function update_clearance_rejects_expired_expiry(): void
    {
        $this->postJson('/api/admin/update-clearance', [
            'clearance' => 'some-clearance-value',
            'userAgent' => 'Mozilla/5.0',
            'expiry'    => time() - 3600,
        ], $this->auth())
            ->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    #[Test]
    public function update_clearance_returns_500_on_unexpected_error(): void
    {
        $apiMock = \Mockery::mock(StakeApiService::class);
        $apiMock->shouldReceive('probeWith')->andReturn(200);
        $this->app->instance(StakeApiService::class, $apiMock);

        $repoMock = \Mockery::mock(ClearanceRepository::class);
        $repoMock->shouldReceive('updateCredentials')->andThrow(new \RuntimeException('Unexpected'));
        $this->app->instance(ClearanceRepository::class, $repoMock);

        $this->postJson('/api/admin/update-clearance', [
            'clearance' => 'valid-clearance-value',
            'userAgent' => 'Mozilla/5.0 Test Browser',
            'expiry'    => time() + 7200,
        ], $this->auth())
            ->assertStatus(500)
            ->assertJson(['success' => false]);
    }

    // -------------------------------------------------------------------------
    // POST /api/admin/test-clearance
    // -------------------------------------------------------------------------

    #[Test]
    public function test_clearance_returns_400_when_credentials_not_configured(): void
    {
        $this->postJson('/api/admin/test-clearance', [], $this->auth())
            ->assertStatus(400)
            ->assertJson(['success' => false]);
    }

    #[Test]
    public function test_clearance_returns_probe_result(): void
    {
        $this->seedCredentials();

        $mock = \Mockery::mock(StakeApiService::class);
        $mock->shouldReceive('probe')->andReturn(200);
        $this->app->instance(StakeApiService::class, $mock);

        $this->postJson('/api/admin/test-clearance', [], $this->auth())
            ->assertOk()
            ->assertJson(['success' => true, 'status_code' => 200]);
    }

    #[Test]
    public function test_clearance_returns_500_on_unexpected_error(): void
    {
        $this->seedCredentials();

        $mock = \Mockery::mock(StakeApiService::class);
        $mock->shouldReceive('probe')->andThrow(new \RuntimeException('Unexpected'));
        $this->app->instance(StakeApiService::class, $mock);

        $this->postJson('/api/admin/test-clearance', [], $this->auth())
            ->assertStatus(500)
            ->assertJson(['success' => false]);
    }

    #[Test]
    public function test_clearance_reports_failure_on_403(): void
    {
        $this->seedCredentials();

        $mock = \Mockery::mock(StakeApiService::class);
        $mock->shouldReceive('probe')->andReturn(403);
        $this->app->instance(StakeApiService::class, $mock);

        $this->postJson('/api/admin/test-clearance', [], $this->auth())
            ->assertOk()
            ->assertJson(['success' => false, 'status_code' => 403]);
    }
}
