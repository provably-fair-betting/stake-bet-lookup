<?php

namespace Stake\BetLookup\Services;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use Illuminate\Support\Facades\Log;
use Stake\BetLookup\Exceptions\AuthenticationException;
use Stake\BetLookup\Exceptions\BetNotFoundException;
use Stake\BetLookup\Exceptions\StakeApiException;

class StakeApiService
{
    private const BET_QUERY = <<<'GQL'
query Bet($iid: String!) {
  bet(iid: $iid) {
    iid
    type
    bet {
      ... on CasinoBet {
        id
        game
        nonce
        clientSeed {
          id
          seed
          __typename
        }
        serverSeed {
          id
          seed
          seedHash
          __typename
        }
        state {
          ... on CasinoGameBars {
            barsDifficulty: difficulty
            barsTiles: tiles
          }
          ... on CasinoGameCases {
            casesDifficulty: difficulty
          }
          ... on CasinoGameChicken {
            chickenDifficulty: difficulty
          }
          ... on CasinoGameDarts {
            dartsDifficulty: difficulty
          }
          ... on CasinoGameDragonTower {
            dragonTowerDifficulty: difficulty
          }
          ... on CasinoGameMines {
            minesCount
          }
          ... on CasinoGameMoles {
            molesCount
          }
          ... on CasinoGamePlinko {
            plinkoRisk: risk
            plinkoRows: rows
          }
          ... on CasinoGamePump {
            pumpDifficulty: difficulty
          }
          ... on CasinoGameSnakes {
            snakesDifficulty: difficulty
          }
          ... on CasinoGameTarot {
            tarotDifficulty: difficulty
          }
          ... on CasinoGameWheel {
            wheelRisk: risk
            wheelSegments: segments
          }
        }
        __typename
      }
      ... on MultiplayerCrashBet {
        id
        crashGame: game {
          id
          seed {
            id
            seed
            __typename
          }
          hash {
            id
            hash
            number
            __typename
          }
          __typename
        }
        __typename
      }
      ... on MultiplayerSlideBet {
        id
        slideGame: game {
          id
          seed {
            id
            seed
            __typename
          }
          hash {
            id
            hash
            number
            __typename
          }
          __typename
        }
        __typename
      }
      __typename
    }
    __typename
  }
}
GQL;

    public function __construct(
        private readonly ClearanceRepository $clearanceRepository,
        private readonly ClearanceAlerter $clearanceAlerter,
        private readonly StakeHttpClientFactory $clientFactory,
    ) {
    }

    /**
     * @throws BetNotFoundException
     * @throws AuthenticationException
     * @throws StakeApiException
     */
    public function fetchBet(string $betId): array
    {
        $credentials = $this->clearanceRepository->getCredentials();

        if (empty($credentials['clearance_cookie']) || empty($credentials['user_agent'])) {
            Log::warning('Stake API: Bet lookup attempted with no clearance credentials configured');

            throw new AuthenticationException(
                'Service temporarily unavailable. Please try again later.',
                503
            );
        }

        if ($this->clearanceRepository->isInMaintenanceMode()) {
            throw new AuthenticationException(
                'Service temporarily unavailable. Please try again later.',
                503
            );
        }

        if ($this->clearanceRepository->isExpiringSoon()) {
            $this->clearanceAlerter->alertExpiringSoon($this->clearanceRepository->getTimeUntilExpiry());
        }

        $betData = $this->requestBet($betId);

        Log::info('Stake API: Bet lookup successful', ['betId' => $betId]);

        return $betData;
    }

    private function requestBet(string $betId): array
    {
        $payload = [
            'query'     => self::BET_QUERY,
            'variables' => ['iid' => $betId],
        ];

        try {
            $credentials = $this->clearanceRepository->getCredentials();
            $response    = $this->clientFactory->makeApiClient($credentials)->post('', ['json' => $payload]);
            $body        = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (ClientException $e) {
            $this->handleClientException($e);
        } catch (ServerException $e) {
            Log::error('Stake API: Server error', ['status' => $e->getResponse()->getStatusCode()]);
            throw new StakeApiException('Stake API server error.', $e->getResponse()->getStatusCode(), $e);
        } catch (ConnectException $e) {
            Log::warning('Stake API: Connection failed', ['message' => $e->getMessage()]);
            throw new StakeApiException('Unable to connect to Stake.games API.', 503, $e);
        }

        if (! empty($body['errors'])) {
            $this->handleGraphQlErrors($body['errors']);
        }

        $betData = $body['data']['bet'] ?? null;

        if ($betData === null) {
            throw new BetNotFoundException("Bet '{$betId}' was not found.");
        }

        return $betData;
    }

    /**
     * @throws AuthenticationException
     * @throws StakeApiException
     */
    private function handleClientException(ClientException $e): never
    {
        $status = $e->getResponse()->getStatusCode();

        if ($status === 403) {
            if (! $this->clearanceRepository->wasAlertSent()) {
                $this->clearanceRepository->enableMaintenanceMode();
                $this->clearanceAlerter->alertExpired();
                $this->clearanceRepository->markAlertSent();
            }

            Log::warning('Stake API: Cloudflare clearance rejected', ['status' => 403]);

            throw new AuthenticationException('Service temporarily unavailable. Please try again later.', 503, $e);
        }

        if ($status === 401) {
            Log::error('Stake API: Access token rejected', ['status' => 401]);

            throw new AuthenticationException('Service temporarily unavailable. Please try again later.', 503, $e);
        }

        Log::error('Stake API: Unexpected client error', ['status' => $status, 'message' => $e->getMessage()]);

        throw new StakeApiException('Service temporarily unavailable. Please try again later.', $status, $e);
    }

    /**
     * @throws AuthenticationException
     * @throws StakeApiException
     */
    private function handleGraphQlErrors(array $errors): never
    {
        foreach ($errors as $error) {
            $message = strtolower($error['message'] ?? '');

            if (str_contains($message, 'unauthorized') || str_contains($message, 'unauthenticated')) {
                throw new AuthenticationException($error['message']);
            }
        }

        throw new StakeApiException('GraphQL error: ' . ($errors[0]['message'] ?? 'Unknown error'));
    }

    public function probe(): int
    {
        $credentials = $this->clearanceRepository->getCredentials();

        if (empty($credentials['clearance_cookie']) || empty($credentials['user_agent'])) {
            return 0;
        }

        return $this->probeWith($credentials['clearance_cookie'], $credentials['user_agent']);
    }

    public function probeWith(string $cookie, string $userAgent): int
    {
        try {
            $response = $this->clientFactory->makeProbeClient()->get('https://stake.games', [
                'headers' => [
                    'Cookie'     => 'cf_clearance=' . $cookie,
                    'User-Agent' => $userAgent,
                ],
            ]);

            $status = $response->getStatusCode();
        } catch (ClientException $e) {
            $status = $e->getResponse()->getStatusCode();
        } catch (\Throwable) {
            $status = 0;
        }

        Log::info('Stake API: Probe completed', ['status' => $status]);

        return $status;
    }
}
