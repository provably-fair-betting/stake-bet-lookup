<?php

namespace Stake\BetLookup\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateBetId
{
    /**
     * Reject requests with obviously malformed bet IDs before they reach the controller.
     * Valid format: house:\d+ (e.g., house:464957124440)
     */
    public function handle(Request $request, Closure $next): Response
    {
        $betId = $request->input('betId', '');

        if (empty($betId)) {
            return response()->json([
                'success' => false,
                'error'   => 'Bet ID is required.',
            ], 400);
        }

        // Stake bet IDs must be in format: house:\d+
        if (!preg_match('/^house:\d+$/', $betId)) {
            return response()->json([
                'success' => false,
                'error'   => 'Invalid bet ID format. Must be in format: house:123456789',
            ], 400);
        }

        return $next($request);
    }
}
