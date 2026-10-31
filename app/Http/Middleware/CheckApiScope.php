<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Enforces API scope (capability) on individual route groups.
 *
 * Usage in routes:  ->middleware('api.scope:write')
 *
 * Scopes:
 *   read  – allowed to query inventory data (stock-balances, barcode, valuation)
 *   write – additionally allowed to create transactions (external-sync)
 *
 * A 'write' token implicitly satisfies a 'read' requirement.
 */
class CheckApiScope
{
    public function handle(Request $request, Closure $next, string $requiredScope): JsonResponse|\Symfony\Component\HttpFoundation\Response
    {
        $tokenScope = $request->attributes->get('api_scope', 'read');

        // Check if user has token with abilities via Sanctum
        $user = $request->user();
        if ($user && method_exists($user, 'currentAccessToken') && $user->currentAccessToken()) {
            $token = $user->currentAccessToken();
            $allowed = match ($requiredScope) {
                'read' => $token->can('read') || $token->can('write') || $token->can('*'),
                'write' => $token->can('write') || $token->can('*'),
                default => false,
            };
        } else {
            // write tokens can also satisfy read requirements
            $allowed = match ($requiredScope) {
                'read' => in_array($tokenScope, ['read', 'write'], true),
                'write' => $tokenScope === 'write',
                default => false,
            };
        }

        if (! $allowed) {
            return response()->json([
                'status' => 'error',
                'message' => "This API token does not have the required '{$requiredScope}' scope.",
            ], 403);
        }

        return $next($request);
    }
}
