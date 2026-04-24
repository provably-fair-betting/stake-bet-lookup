<?php

namespace Stake\BetLookup\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Stake\BetLookup\Services\StakeApiService;
use Stake\BetLookup\Tests\TestCase;

class BetLookupTest extends TestCase
{
    private function mockStakeApiResponse(array $data): void
    {
        $mock = \Mockery::mock(StakeApiService::class);
        $mock->shouldReceive('fetchBet')
            ->andReturn($data);

        $this->app->instance(StakeApiService::class, $mock);
    }

    private function mockStakeApiException(string $exceptionClass, string $message = 'Error', int $code = 0): void
    {
        $mock = \Mockery::mock(StakeApiService::class);
        $mock->shouldReceive('fetchBet')
            ->andThrow(new $exceptionClass($message, $code));

        $this->app->instance(StakeApiService::class, $mock);
    }

    private function casinoBetResponse(): array
    {
        return [
            '__typename' => 'Bet',
            'iid'        => 'abc123',
            'type'       => 'casino',
            'bet'        => [
                '__typename' => 'CasinoBet',
                'id'         => '1',
                'game'       => 'dice',
                'nonce'      => 42,
                'clientSeed' => [
                    'id'         => 'cs1',
                    'seed'       => 'myclientseed',
                    '__typename' => 'UserSeed',
                ],
                'serverSeed' => [
                    'id'         => 'ss1',
                    'seed'       => 'myserverseed',
                    'seedHash'   => 'abc123hash',
                    '__typename' => 'ServerSeed',
                ],
            ],
        ];
    }

    #[Test]
    public function it_returns_normalized_casino_bet_data(): void
    {
        $this->mockStakeApiResponse($this->casinoBetResponse());

        $response = $this->postJson('/api/bet-lookup', ['betId' => 'house:464957124440']);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data'    => [
                    'betType' => 'CasinoBet',
                    'game'    => 'dice',
                    'inputs'  => [
                        'clientSeed'     => 'myclientseed',
                        'serverSeed'     => 'myserverseed',
                        'serverSeedHash' => 'abc123hash',
                        'nonce'          => 42,
                    ],
                ],
            ]);
    }

    #[Test]
    public function it_returns_404_when_bet_not_found(): void
    {
        $this->mockStakeApiException(\Stake\BetLookup\Exceptions\BetNotFoundException::class);

        $response = $this->postJson('/api/bet-lookup', ['betId' => 'house:999999999999']);

        $response->assertStatus(404)
            ->assertJson(['success' => false]);
    }

    #[Test]
    public function it_returns_503_on_api_failure(): void
    {
        $this->mockStakeApiException(\Stake\BetLookup\Exceptions\StakeApiException::class);

        $response = $this->postJson('/api/bet-lookup', ['betId' => 'house:123456789']);

        $response->assertStatus(503)
            ->assertJson(['success' => false]);
    }

    #[Test]
    public function it_returns_503_when_in_maintenance_mode(): void
    {
        $this->mockStakeApiException(\Stake\BetLookup\Exceptions\AuthenticationException::class, 'Maintenance mode', 503);

        $this->postJson('/api/bet-lookup', ['betId' => 'house:123456789'])
            ->assertStatus(503)
            ->assertJson(['success' => false]);
    }

    #[Test]
    public function it_returns_401_on_authentication_failure(): void
    {
        $this->mockStakeApiException(\Stake\BetLookup\Exceptions\AuthenticationException::class, 'Unauthorized', 401);

        $this->postJson('/api/bet-lookup', ['betId' => 'house:123456789'])
            ->assertStatus(401)
            ->assertJson(['success' => false]);
    }

    #[Test]
    public function it_returns_500_on_unexpected_error(): void
    {
        $mock = \Mockery::mock(StakeApiService::class);
        $mock->shouldReceive('fetchBet')->andThrow(new \RuntimeException('Unexpected'));
        $this->app->instance(StakeApiService::class, $mock);

        $this->postJson('/api/bet-lookup', ['betId' => 'house:123456789'])
            ->assertStatus(500)
            ->assertJson(['success' => false]);
    }

    #[Test]
    public function it_returns_422_when_server_seed_not_revealed(): void
    {
        $mock = \Mockery::mock(StakeApiService::class);
        $mock->shouldReceive('fetchBet')
            ->andReturn([
                'bet' => [
                    '__typename' => 'CasinoBet',
                    'id'         => '1',
                    'game'       => 'dice',
                    'nonce'      => 1,
                    'clientSeed' => ['seed' => 'abc', '__typename' => 'UserSeed'],
                    'serverSeed' => null,
                ],
            ]);

        $this->app->instance(StakeApiService::class, $mock);

        $this->postJson('/api/bet-lookup', ['betId' => 'house:123456789'])
            ->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    #[Test]
    public function it_rejects_empty_bet_id(): void
    {
        $response = $this->postJson('/api/bet-lookup', ['betId' => '']);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error'   => 'Bet ID is required.',
            ]);
    }

    #[Test]
    public function it_rejects_bet_id_with_invalid_characters(): void
    {
        $response = $this->postJson('/api/bet-lookup', ['betId' => '<script>alert(1)</script>']);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error'   => 'Invalid bet ID format. Must be in format: house:123456789',
            ]);
    }

    #[Test]
    public function it_rejects_missing_bet_id(): void
    {
        $response = $this->postJson('/api/bet-lookup', []);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error'   => 'Bet ID is required.',
            ]);
    }

    #[Test]
    public function it_rejects_bet_id_without_house_prefix(): void
    {
        $response = $this->postJson('/api/bet-lookup', ['betId' => '464957124440']);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error'   => 'Invalid bet ID format. Must be in format: house:123456789',
            ]);
    }

    #[Test]
    public function it_rejects_bet_id_with_wrong_prefix(): void
    {
        $response = $this->postJson('/api/bet-lookup', ['betId' => 'casino:464957124440']);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error'   => 'Invalid bet ID format. Must be in format: house:123456789',
            ]);
    }

    #[Test]
    public function it_rejects_bet_id_with_non_numeric_id(): void
    {
        $response = $this->postJson('/api/bet-lookup', ['betId' => 'house:abc123']);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error'   => 'Invalid bet ID format. Must be in format: house:123456789',
            ]);
    }

    #[Test]
    public function it_accepts_valid_bet_id_format(): void
    {
        $this->mockStakeApiResponse($this->casinoBetResponse());

        $response = $this->postJson('/api/bet-lookup', ['betId' => 'house:464957124440']);

        $response->assertOk();
    }
}
