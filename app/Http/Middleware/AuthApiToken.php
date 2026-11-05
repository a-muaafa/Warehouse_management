<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates external API requests via Sanctum personal access tokens,
 * with fallback to configured tokens.
 */
class AuthApiToken
{
    public function handle(Request $request, Closure $next): JsonResponse|Response
    {
        $providedToken = $this->extractBearerToken($request);

        if (! $providedToken) {
            return response()->json([
                'success' => false,
                'success' => false,
                'status' => 'error',
                'message' => 'API authentication required. Provide a Bearer token.',
                'data' => null,
                'data' => null,
            ], 401);
        }

        // 1. Check Sanctum Personal Access Token
        $pat = PersonalAccessToken::findToken($providedToken);
        if ($pat) {
            if ($pat->expires_at && $pat->expires_at->isPast()) {
                return response()->json([
                    'success' => false,
                    'success' => false,
                    'status' => 'error',
                    'message' => 'API token has expired.',
                    'data' => null,
                    'data' => null,
                ], 401);
            }

            $user = $pat->tokenable;
            if ($user && ! $user->is_active) {
                return response()->json([
                    'success' => false,
                    'success' => false,
                    'status' => 'error',
                    'message' => 'User account is deactivated.',
                    'data' => null,
                    'data' => null,
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

        // 2. Check configured integration tokens (SHA-256 hashed comparison + expiration check)
        $tokens = config('api.tokens', []);
        $matchedToken = null;
        $providedHash = hash('sha256', $providedToken);

        foreach ($tokens as $tokenEntry) {
            $expectedHash = $tokenEntry['hash'] ?? (isset($tokenEntry['token']) ? hash('sha256', (string) $tokenEntry['token']) : '');
            if ($expectedHash !== '' && hash_equals($expectedHash, $providedHash)) {
                if (! empty($tokenEntry['expires_at']) && now()->gte(Carbon::parse($tokenEntry['expires_at']))) {
                    return response()->json([
                        'success' => false,
                        'status' => 'error',
                        'message' => 'API token has expired.',
                        'data' => null,
                    ], 401);
                }

                $matchedToken = $tokenEntry;
                break;
            }
        }

        if (! $matchedToken) {
            return response()->json([
                'success' => false,
                'success' => false,
                'status' => 'error',
                'message' => 'Invalid or revoked API token.',
                'data' => null,
                'data' => null,
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
