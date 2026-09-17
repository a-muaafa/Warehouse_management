<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        if (Auth::attempt(['username' => $credentials['username'], 'password' => $credentials['password']], $request->boolean('remember'))) {
            $user = Auth::user();

            if (! $user->is_active) {
                Auth::logout();

                return back()->withErrors([
                    'username' => __('حسابك معطل حالياً. يرجى التوصل مع رئيس القسم أو المدير العام.'),
                ])->onlyInput('username');
            }

            $request->session()->regenerate();
            ActivityLog::log('تسجيل دخول', 'قام المستخدم بتسجيل الدخول إلى النظام');

            return redirect()->intended(route('dashboard'));
        }

        return back()->withErrors([
            'username' => __('بيانات الدخول غير صحيحة. يرجى التأكد من اسم المستخدم وكلمة المرور.'),
        ])->onlyInput('username');
    }

    public function logout(Request $request): RedirectResponse
    {
        if (Auth::check()) {
            ActivityLog::log('تسجيل خروج', 'قام المستخدم بتسجيل الخروج من النظام');
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
