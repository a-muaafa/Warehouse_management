<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Authenticates external API requests via Sanctum personal access tokens,
 * with fallback to configured tokens.
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

        // 1. Check Sanctum Personal Access Token
        $pat = PersonalAccessToken::findToken($providedToken);
        if ($pat) {
            if ($pat->expires_at && $pat->expires_at->isPast()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'API token has expired.',
                ], 401);
            }

            $user = $pat->tokenable;
            if ($user && ! $user->is_active) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User account is deactivated.',
                ], 403);
            }

            if ($user) {
                auth()->setUser($user);
                $request->setUserResolver(fn () => $user);
            }

            $scope = ($pat->can('write') || $pat->can('*')) ? 'write' : 'read';
            $request->attributes->set('api_token', [
                'name' => $pat->name,
                'scope' => $scope,
            ]);
            $request->attributes->set('api_scope', $scope);

            return $next($request);
        }

        // 2. Check pre-shared config tokens (legacy fallback)
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
