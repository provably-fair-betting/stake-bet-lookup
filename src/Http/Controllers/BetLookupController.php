<?php

namespace Stake\BetLookup\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Stake\BetLookup\Services\BetNormalizerService;
use Stake\BetLookup\Services\StakeApiService;
use Throwable;

class BetLookupController extends Controller
{
    public function __construct(
        private readonly StakeApiService $stakeApiService,
        private readonly BetNormalizerService $normalizerService,
    ) {}

    public function lookup(Request $request): JsonResponse
    {
        // Validation handled by ValidateBetId middleware
        $betId = $request->input('betId');

        try {
            $rawResponse = $this->stakeApiService->fetchBet($betId);
            $normalized = $this->normalizerService->normalize($rawResponse);

            return response()->json([
                'success' => true,
                'data'    => $normalized,
            ]);
        } catch (\Stake\BetLookup\Exceptions\SeedNotRevealedException $e) {
            return response()->json([
                'success' => false,
                'error'   => 'Server seed has not been revealed yet. Please try again later.',
            ], 422);
        } catch (\Stake\BetLookup\Exceptions\BetNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error'   => 'Bet not found. Please check the ID and try again.',
            ], 404);
        } catch (\Stake\BetLookup\Exceptions\StakeApiException $e) {
            return response()->json([
                'success' => false,
                'error'   => 'Unable to fetch bet data. Please try again later.',
            ], 503);
        } catch (\Stake\BetLookup\Exceptions\AuthenticationException $e) {
            // Return user-friendly message from the exception (includes maintenance mode messages)
            return response()->json([
                'success' => false,
                'error'   => $e->getMessage(),
                'retry_after' => 60, // Suggest retry after 60 seconds
            ], $e->getCode() ?: 503);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error'   => 'An unexpected error occurred. Please try again later.',
            ], 500);
        }
    }
}
