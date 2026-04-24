<?php

namespace Stake\BetLookup\Tests\Unit;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use Stake\BetLookup\Exceptions\AuthenticationException;
use Stake\BetLookup\Exceptions\BetNotFoundException;
use Stake\BetLookup\Exceptions\StakeApiException;
use Stake\BetLookup\Services\ClearanceAlerter;
use Stake\BetLookup\Services\ClearanceRepository;
use Stake\BetLookup\Services\StakeApiService;
use Stake\BetLookup\Services\StakeHttpClientFactory;

class StakeApiServiceTest extends \Stake\BetLookup\Tests\TestCase
{
    private function makeService(
        array $responses,
        array $configOverrides = [],
        ?ClearanceRepository $repo = null,
        ?ClearanceAlerter $alerter = null,
        array &$history = []
    ): StakeApiService {
        $handler = HandlerStack::create(new MockHandler($responses));
        $handler->push(Middleware::history($history));

        if ($repo === null) {
            $repo = new ClearanceRepository(['clearance_warning_threshold' => 3600]);
            $repo->updateCredentials('test-clearance-cookie', 'Mozilla/5.0 Test Browser', time() + 7200);
        }

        $factoryConfig = array_merge([
            'stake_api_url'      => 'https://stake.games/_api/graphql',
            'stake_access_token' => null,
            'timeout'            => 10,
        ], $configOverrides);

        return new StakeApiService(
            $repo,
            $alerter ?? new ClearanceAlerter(['clearance_alert_email' => null, 'clearance_alert_slack_webhook' => null]),
            new StakeHttpClientFactory($factoryConfig, $handler),
        );
    }

    private function successPayload(): string
    {
        return json_encode([
            'data' => [
                'bet' => [
                    '__typename' => 'Bet',
                    'iid'        => 'abc123',
                    'type'       => 'casino',
                    'bet'        => [
                        '__typename' => 'CasinoBet',
                        'id'         => '1',
                        'game'       => 'dice',
                        'nonce'      => 5,
                        'clientSeed' => ['id' => 'cs1', 'seed' => 'clientseed', '__typename' => 'UserSeed'],
                        'serverSeed' => ['id' => 'ss1', 'seed' => 'serverseed', 'seedHash' => 'hash123', '__typename' => 'ServerSeed'],
                    ],
                ],
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // fetchBet
    // -------------------------------------------------------------------------

    #[Test]
    public function it_throws_authentication_exception_when_credentials_not_configured(): void
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionCode(503);

        $repo = new ClearanceRepository(['clearance_warning_threshold' => 3600]);

        $this->makeService([], [], $repo)->fetchBet('abc123');
    }

    #[Test]
    public function it_returns_bet_data_on_success(): void
    {
        $result = $this->makeService([
            new Response(200, [], $this->successPayload()),
        ])->fetchBet('abc123');

        $this->assertEquals('abc123', $result['iid']);
    }

    #[Test]
    public function it_throws_authentication_exception_when_in_maintenance_mode(): void
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionCode(503);

        $repo = new ClearanceRepository(['clearance_warning_threshold' => 3600]);
        $repo->enableMaintenanceMode();

        $this->makeService([], [], $repo)->fetchBet('abc123');
    }

    #[Test]
    public function it_sends_expiring_soon_alert_when_clearance_is_expiring(): void
    {
        $repo = new ClearanceRepository(['clearance_warning_threshold' => 3600]);
        $repo->updateCredentials('test-cookie', 'Mozilla/5.0', time() + 1800);

        $alerter = $this->createMock(ClearanceAlerter::class);
        $alerter->expects($this->once())
            ->method('alertExpiringSoon')
            ->with($this->isInt());

        $this->makeService(
            [new Response(200, [], $this->successPayload())],
            [],
            $repo,
            $alerter
        )->fetchBet('abc123');
    }

    #[Test]
    public function it_includes_access_token_header_when_configured(): void
    {
        $history = [];
        $this->makeService(
            [new Response(200, [], $this->successPayload())],
            ['stake_access_token' => 'my-access-token'],
            history: $history
        )->fetchBet('abc123');

        $this->assertEquals('my-access-token', $history[0]['request']->getHeaderLine('x-access-token'));
    }

    #[Test]
    public function it_throws_bet_not_found_when_data_is_null(): void
    {
        $this->expectException(BetNotFoundException::class);

        $this->makeService([
            new Response(200, [], json_encode(['data' => ['bet' => null]])),
        ])->fetchBet('nonexistent');
    }

    #[Test]
    public function it_throws_stake_api_exception_on_graphql_errors(): void
    {
        $this->expectException(StakeApiException::class);

        $this->makeService([
            new Response(200, [], json_encode(['errors' => [['message' => 'Invalid input']]])),
        ])->fetchBet('abc123');
    }

    #[Test]
    public function it_throws_authentication_exception_on_graphql_auth_error(): void
    {
        $this->expectException(AuthenticationException::class);

        $this->makeService([
            new Response(200, [], json_encode(['errors' => [['message' => 'Unauthorized access']]])),
        ])->fetchBet('abc123');
    }

    #[Test]
    public function it_throws_authentication_exception_on_403_clearance_failure(): void
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionCode(503);

        $this->makeService([
            new \GuzzleHttp\Exception\ClientException(
                'Forbidden',
                new Request('POST', '/'),
                new Response(403, [], 'Access Denied')
            ),
        ])->fetchBet('abc123');
    }

    #[Test]
    public function it_throws_authentication_exception_on_401(): void
    {
        $this->expectException(AuthenticationException::class);

        $this->makeService([
            new \GuzzleHttp\Exception\ClientException(
                'Unauthorized',
                new Request('POST', '/'),
                new Response(401, [], 'Unauthorized')
            ),
        ])->fetchBet('abc123');
    }

    #[Test]
    public function it_throws_stake_api_exception_on_unhandled_client_error(): void
    {
        $this->expectException(StakeApiException::class);

        $this->makeService([
            new \GuzzleHttp\Exception\ClientException(
                'Unprocessable Entity',
                new Request('POST', '/'),
                new Response(422, [], 'Validation Error')
            ),
        ])->fetchBet('abc123');
    }

    #[Test]
    public function it_throws_stake_api_exception_on_server_error(): void
    {
        $this->expectException(StakeApiException::class);

        $this->makeService([
            new \GuzzleHttp\Exception\ServerException(
                'Internal Server Error',
                new Request('POST', '/'),
                new Response(500)
            ),
        ])->fetchBet('abc123');
    }

    #[Test]
    public function it_throws_stake_api_exception_on_connection_failure(): void
    {
        $this->expectException(StakeApiException::class);

        $this->makeService([
            new ConnectException('Connection refused', new Request('POST', '/')),
        ])->fetchBet('abc123');
    }

    // -------------------------------------------------------------------------
    // probe
    // -------------------------------------------------------------------------

    #[Test]
    public function probe_returns_zero_when_credentials_not_set(): void
    {
        $config  = ['stake_api_url' => 'https://stake.games/_api/graphql', 'timeout' => 10];
        $handler = HandlerStack::create(new MockHandler([]));

        $service = new StakeApiService(
            new ClearanceRepository(['clearance_warning_threshold' => 3600]),
            new ClearanceAlerter(['clearance_alert_email' => null, 'clearance_alert_slack_webhook' => null]),
            new StakeHttpClientFactory($config, $handler),
        );

        $this->assertEquals(0, $service->probe());
    }

    #[Test]
    public function probe_returns_200_on_success(): void
    {
        $this->assertEquals(200, $this->makeService([new Response(200)])->probe());
    }

    #[Test]
    public function probe_returns_403_on_clearance_failure(): void
    {
        $result = $this->makeService([
            new \GuzzleHttp\Exception\ClientException(
                'Forbidden',
                new Request('GET', '/'),
                new Response(403)
            ),
        ])->probe();

        $this->assertEquals(403, $result);
    }

    #[Test]
    public function probe_returns_zero_on_connection_failure(): void
    {
        $result = $this->makeService([
            new ConnectException('Connection refused', new Request('GET', '/')),
        ])->probe();

        $this->assertEquals(0, $result);
    }

    // -------------------------------------------------------------------------
    // probeWith
    // -------------------------------------------------------------------------

    #[Test]
    public function probe_with_returns_200_on_success(): void
    {
        $result = $this->makeService([new Response(200)])
            ->probeWith('test-cookie', 'Mozilla/5.0');

        $this->assertEquals(200, $result);
    }

    #[Test]
    public function probe_with_returns_403_on_clearance_failure(): void
    {
        $result = $this->makeService([
            new \GuzzleHttp\Exception\ClientException(
                'Forbidden',
                new Request('GET', '/'),
                new Response(403)
            ),
        ])->probeWith('bad-cookie', 'Mozilla/5.0');

        $this->assertEquals(403, $result);
    }

    #[Test]
    public function probe_with_returns_zero_on_connection_failure(): void
    {
        $result = $this->makeService([
            new ConnectException('Connection refused', new Request('GET', '/')),
        ])->probeWith('test-cookie', 'Mozilla/5.0');

        $this->assertEquals(0, $result);
    }
}
