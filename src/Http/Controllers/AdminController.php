<?php

namespace Stake\BetLookup\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Stake\BetLookup\Services\ClearanceRepository;
use Stake\BetLookup\Services\StakeApiService;
use Throwable;

class AdminController extends Controller
{
    public function __construct(
        private readonly ClearanceRepository $clearanceRepository,
        private readonly StakeApiService $stakeApiService,
    ) {
    }

    public function updateClearance(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'clearance' => ['required', 'string', 'min:10'],
                'userAgent' => ['required', 'string', 'min:10'],
                'expiry'    => ['required', 'integer', 'min:' . time()],
            ]);

            $probeStatus = $this->stakeApiService->probeWith($validated['clearance'], $validated['userAgent']);

            if ($probeStatus !== 200) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Credentials rejected by Stake.games',
                    'message' => "Probe returned HTTP {$probeStatus}. Credentials were not saved.",
                ], 422);
            }

            $this->clearanceRepository->updateCredentials(
                $validated['clearance'],
                $validated['userAgent'],
                $validated['expiry'],
                $request->input('updatedBy', $request->ip())
            );

            return response()->json([
                'success'    => true,
                'message'    => 'Clearance credentials updated successfully',
                'expires_at' => date('Y-m-d H:i:s', $validated['expiry']),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error'   => 'Validation failed',
                'details' => $e->errors(),
            ], 422);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error'   => 'Failed to update clearance credentials',
            ], 500);
        }
    }

    public function getStatus(): JsonResponse
    {
        $credentials = $this->clearanceRepository->getCredentials();

        return response()->json([
            'success' => true,
            'data'    => [
                'is_valid'            => $this->clearanceRepository->isValid(),
                'is_expiring_soon'    => $this->clearanceRepository->isExpiringSoon(),
                'status'              => $this->clearanceRepository->getExpiryStatus(),
                'time_until_expiry'   => $this->clearanceRepository->getTimeUntilExpiry(),
                'expires_at'          => $credentials['expiry'] ? date('Y-m-d H:i:s', $credentials['expiry']) : null,
                'clearance_cookie_set' => ! empty($credentials['clearance_cookie']),
                'user_agent_set'      => ! empty($credentials['user_agent']),
                'maintenance_mode'    => $this->clearanceRepository->isInMaintenanceMode(),
            ],
        ]);
    }

    public function getCredentials(): JsonResponse
    {
        $credentials = $this->clearanceRepository->getCredentials();

        if (! $credentials['clearance_cookie'] || ! $credentials['user_agent']) {
            return response()->json([
                'success' => false,
                'error'   => 'Clearance credentials not configured',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'clearance_cookie' => $credentials['clearance_cookie'],
                'user_agent'       => $credentials['user_agent'],
                'expires_at'       => $credentials['expiry'] ? date('Y-m-d H:i:s', $credentials['expiry']) : null,
            ],
        ]);
    }

    public function testClearance(): JsonResponse
    {
        $credentials = $this->clearanceRepository->getCredentials();

        if (! $credentials['clearance_cookie'] || ! $credentials['user_agent']) {
            return response()->json([
                'success' => false,
                'error'   => 'Clearance credentials not configured',
            ], 400);
        }

        try {
            $status = $this->stakeApiService->probe();

            return response()->json([
                'success'     => $status === 200,
                'message'     => $status === 200 ? 'Clearance is working' : 'Clearance may be expired',
                'status_code' => $status,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error'   => 'Test request failed',
            ], 500);
        }
    }
}
