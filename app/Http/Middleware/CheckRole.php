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
        if (! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['username' => __('تم تعطيل حسابك. يرجى التواصل مع المسؤول.')]);
        }

        // Super Admin always has full access
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        // Check explicit role match
        if (in_array($user->role, $roles, true)) {
            return $next($request);
        }

        // Check role aliases for legacy compatibility
        $hasCompatibleRole = false;
        foreach ($roles as $role) {
            if ($role === 'admin' && $user->isAdmin()) {
                $hasCompatibleRole = true;
                break;
            }
            if ($role === 'supervisor' && $user->isSupervisor()) {
                $hasCompatibleRole = true;
                break;
            }
            if ($role === 'worker' && $user->isWorker()) {
                $hasCompatibleRole = true;
                break;
            }
        }

        if ($hasCompatibleRole) {
            return $next($request);
        }

        abort(403, 'Unauthorized action.');
    }
}
