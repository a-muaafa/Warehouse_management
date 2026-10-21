<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Authenticates external API requests via a pre-shared Bearer token.
 *
 * Token configuration lives in config/api.php under the 'tokens' key.
 * Each token entry defines its 'scope' ('read' or 'write') so downstream
 * middleware (CheckApiScope) can enforce capability-level access control.
 *
 * A missing or invalid token always returns 401 — no fallback to any user ID.
 */
class AuthApiToken
{
    public function handle(Request $request, Closure $next): JsonResponse|\Symfony\Component\HttpFoundation\Response
    {
        $providedToken = $this->extractBearerToken($request);

        if (! $providedToken) {
            return response()->json([
                'status' => 'error',
                'message' => 'API authentication required. Provide a Bearer token.',
            ], 401);
        }

        $tokens = config('api.tokens', []);
        $matchedToken = null;

        foreach ($tokens as $tokenEntry) {
            if (hash_equals($tokenEntry['token'] ?? '', $providedToken)) {
                $matchedToken = $tokenEntry;
                break;
            }
        }

        if (! $matchedToken) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid or revoked API token.',
            ], 401);
        }

        // Attach the resolved token metadata to the request for downstream use
        $request->attributes->set('api_token', $matchedToken);
        $request->attributes->set('api_scope', $matchedToken['scope'] ?? 'read');

        return $next($request);
    }

    /**
     * Extract the raw token value from "Authorization: Bearer <token>"
     */
    private function extractBearerToken(Request $request): ?string
    {
        $header = $request->header('Authorization', '');

        if (str_starts_with($header, 'Bearer ')) {
            $token = trim(substr($header, 7));

            return $token !== '' ? $token : null;
        }

        return null;
    }
}
