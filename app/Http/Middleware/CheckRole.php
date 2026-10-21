<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403, 'Unauthorized action.');
        }

        // Always reject disabled accounts, even if their session is still active.
        // This catches accounts that were deactivated after they had already logged in.
        if (! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['username' => __('تم تعطيل حسابك. يرجى التواصل مع المسؤول.')]);
        }

        // Admin always has full access
        if ($user->isAdmin()) {
            return $next($request);
        }

        if (! in_array($user->role, $roles)) {
            abort(403, 'Unauthorized action.');
        }

        return $next($request);
    }
}
