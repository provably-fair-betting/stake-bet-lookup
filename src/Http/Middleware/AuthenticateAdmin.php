<?php

namespace Stake\BetLookup\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            Log::warning('Stake API: Admin access attempt with no token', [
                'ip'  => $request->ip(),
                'url' => $request->fullUrl(),
            ]);

            return $this->unauthorized('Admin token required.');
        }

        $expected = config('bet-lookup.admin_token');

        if (! $expected) {
            Log::error('Stake API: Admin token not configured in environment');

            return response()->json(['success' => false, 'error' => 'Admin authentication not configured.'], 500);
        }

        if (! hash_equals($expected, hash('sha256', $token))) {
            Log::warning('Stake API: Admin access attempt with invalid token', [
                'ip'  => $request->ip(),
                'url' => $request->fullUrl(),
            ]);

            return $this->unauthorized('Invalid admin token.');
        }

        Log::info('Stake API: Admin authenticated', [
            'ip'       => $request->ip(),
            'endpoint' => $request->path(),
        ]);

        return $next($request);
    }

    private function unauthorized(string $message): Response
    {
        return response()->json(['success' => false, 'error' => $message], 401);
    }
}
