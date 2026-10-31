<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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

        $throttleKey = Str::transliterate(Str::lower($credentials['username']).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            ActivityLog::create([
                'user_id' => null,
                'user_name' => $credentials['username'],
                'action' => 'حظر مؤقت لمحاولات الدخول',
                'description' => "تجاوز الحد الأقصى لمحاولات تسجيل الدخول من العنوان: {$request->ip()}",
                'ip_address' => $request->ip(),
            ]);

            throw ValidationException::withMessages([
                'username' => [__('محاولات دخول كثيرة خاطئة. يرجى الانتظار :seconds ثانية قبل المحاولة مرة أخرى.', ['seconds' => $seconds])],
            ]);
        }

        if (Auth::attempt(['username' => $credentials['username'], 'password' => $credentials['password']], $request->boolean('remember'))) {
            RateLimiter::clear($throttleKey);
            $user = Auth::user();

            if (! $user->is_active) {
                Auth::logout();

                return back()->withErrors([
                    'username' => __('حسابك معطل حالياً. يرجى التواصل مع مسؤول النظام.'),
                ])->onlyInput('username');
            }

            $request->session()->regenerate();
            ActivityLog::log('تسجيل دخول', 'قام المستخدم بتسجيل الدخول إلى النظام');

            return redirect()->intended(route('dashboard'));
        }

        RateLimiter::hit($throttleKey, 60);

        ActivityLog::create([
            'user_id' => null,
            'user_name' => $credentials['username'],
            'action' => 'فشل تسجيل الدخول',
            'description' => "محاولة تسجيل دخول فاشلة لاسم المستخدم: {$credentials['username']}",
            'ip_address' => $request->ip(),
        ]);

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
