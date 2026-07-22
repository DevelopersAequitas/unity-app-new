<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\UserSessionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSingleActiveSession
{
    public function __construct(
        protected UserSessionService $sessionService
    ) {}

    /**
     * Handle an incoming request and ensure the user token's session_id matches Redis.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'code' => 'UNAUTHENTICATED',
                'message' => 'Unauthenticated access.',
            ], 401);
        }

        $sessionId = $this->extractSessionIdFromRequest($request);

        if (! $sessionId) {
            return response()->json([
                'code' => 'SESSION_MISSING',
                'message' => 'No active session identifier found in token.',
            ], 401);
        }

        // Validate session_id against Redis active session for user
        $isValid = $this->sessionService->isSessionValid((string) $user->id, $sessionId);

        if (! $isValid) {
            return response()->json([
                'code' => 'SESSION_SUPERSEDED',
                'message' => 'You have been logged out because your account was accessed on another device.',
            ], 401);
        }

        return $next($request);
    }

    /**
     * Extract session_id from JWT payload claim or Sanctum token metadata.
     */
    protected function extractSessionIdFromRequest(Request $request): ?string
    {
        // 1. Check if token object attached by Sanctum has a session_id claim / property
        $token = $request->user()?->currentAccessToken();
        if ($token && isset($token->session_id)) {
            return (string) $token->session_id;
        }

        // 2. Check X-Session-ID header if passed explicitly by client
        if ($request->hasHeader('X-Session-ID')) {
            return $request->header('X-Session-ID');
        }

        // 3. Extract and parse JWT Bearer payload claim (jti or session_id)
        $bearerToken = $request->bearerToken();
        if ($bearerToken && str_contains($bearerToken, '.')) {
            $parts = explode('.', $bearerToken);
            if (count($parts) === 3) {
                $payloadJson = base64_decode(str_tr($parts[1], '-_', '+/'));
                $payload = json_decode($payloadJson, true);
                if (is_array($payload)) {
                    return $payload['session_id'] ?? $payload['jti'] ?? null;
                }
            }
        }

        // 4. Check Sanctum token name if set to session_id format
        if ($token && ! empty($token->name)) {
            if (str_contains($token->name, 'session:')) {
                return str_replace('session:', '', $token->name);
            }

            // Fallback for Sanctum personal access tokens
            return 'token:'.$token->id;
        }

        return null;
    }
}

/**
 * Helper to standardise base64url decode
 */
if (! function_exists('App\Http\Middleware\str_tr')) {
    function str_tr(string $string, string $from, string $to): string
    {
        return strtr($string, $from, $to);
    }
}
