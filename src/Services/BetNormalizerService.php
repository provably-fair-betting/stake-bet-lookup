<?php

namespace Stake\BetLookup\Services;

use Stake\BetLookup\Exceptions\SeedNotRevealedException;
use Stake\BetLookup\Exceptions\StakeApiException;

class BetNormalizerService
{
    /**
     * Normalize raw Stake.games bet data into the unified verifier format.
     *
     * @param  array  $betData  Raw data from the GraphQL response (the `bet` node).
     * @return array            Normalized data for the frontend.
     *
     * @throws StakeApiException
     */
    public function normalize(array $betData): array
    {
        $innerBet = $betData['bet'] ?? null;
        $betType  = $innerBet['__typename'] ?? null;

        return match ($betType) {
            'CasinoBet'           => $this->normalizeCasinoBet($innerBet),
            'MultiplayerCrashBet' => $this->normalizeMultiplayerCrashBet($innerBet),
            'MultiplayerSlideBet' => $this->normalizeMultiplayerSlideBet($innerBet),
            default               => throw new StakeApiException("Unknown bet type: {$betType}"),
        };
    }

    /**
     * Normalize a CasinoBet.
     */
    private function normalizeCasinoBet(array $bet): array
    {
        if (empty($bet['serverSeed']['seed'])) {
            throw new SeedNotRevealedException('Server seed has not been revealed for this bet yet.');
        }

        $inputs = [
            'clientSeed'     => $bet['clientSeed']['seed'] ?? null,
            'serverSeed'     => $bet['serverSeed']['seed'],
            'serverSeedHash' => $bet['serverSeed']['seedHash'] ?? null,
            'nonce'          => $bet['nonce'] ?? null,
        ];

        $stateInputs = $this->extractStateInputs($bet['state'] ?? []);
        if (! empty($stateInputs)) {
            $inputs = array_merge($inputs, $stateInputs);
        }

        return [
            'betType' => 'CasinoBet',
            'game'    => $bet['game'] ?? null,
            'inputs'  => $inputs,
        ];
    }

    private function extractStateInputs(?array $state): array
    {
        if (empty($state)) {
            return [];
        }

        if (array_key_exists('barsDifficulty', $state)) {
            return array_filter([
                'difficulty' => $state['barsDifficulty'],
                'tiles'      => $state['barsTiles'] ?? null,
            ], fn ($v) => $v !== null);
        }

        foreach (['casesDifficulty', 'chickenDifficulty', 'dartsDifficulty',
            'dragonTowerDifficulty', 'pumpDifficulty', 'snakesDifficulty', 'tarotDifficulty'] as $key) {
            if (array_key_exists($key, $state)) {
                return ['difficulty' => $state[$key]];
            }
        }

        if (array_key_exists('minesCount', $state)) {
            return ['minesCount' => $state['minesCount']];
        }

        if (array_key_exists('molesCount', $state)) {
            return ['molesCount' => $state['molesCount']];
        }

        if (array_key_exists('plinkoRisk', $state)) {
            return array_filter([
                'risk' => $state['plinkoRisk'],
                'rows' => $state['plinkoRows'] ?? null,
            ], fn ($v) => $v !== null);
        }

        if (array_key_exists('wheelRisk', $state)) {
            return array_filter([
                'risk'     => $state['wheelRisk'],
                'segments' => $state['wheelSegments'] ?? null,
            ], fn ($v) => $v !== null);
        }

        return [];
    }

    /**
     * Normalize a MultiplayerCrashBet (Phase 2).
     */
    private function normalizeMultiplayerCrashBet(array $bet): array
    {
        $game = $bet['crashGame'] ?? [];

        if (empty($game['seed']['seed'])) {
            throw new SeedNotRevealedException('Server seed has not been revealed for this bet yet.');
        }

        return [
            'betType' => 'MultiplayerCrashBet',
            'game'    => 'crash',
            'inputs'  => [
                'serverSeed' => $game['seed']['seed'],
                'gameHash'   => $game['hash']['hash'] ?? null,
            ],
        ];
    }

    /**
     * Normalize a MultiplayerSlideBet (Phase 2).
     */
    private function normalizeMultiplayerSlideBet(array $bet): array
    {
        $game = $bet['slideGame'] ?? [];

        if (empty($game['seed']['seed'])) {
            throw new SeedNotRevealedException('Server seed has not been revealed for this bet yet.');
        }

        return [
            'betType' => 'MultiplayerSlideBet',
            'game'    => 'slide',
            'inputs'  => [
                'serverSeed' => $game['seed']['seed'],
                'gameHash'   => $game['hash']['hash'] ?? null,
            ],
        ];
    }
}
