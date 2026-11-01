<?php

namespace App\Services\System;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Session authentication + password reset (SMTP). */
class AuthService
{
    public function __construct(private readonly UserService $users)
    {
    }

    public function login(Request $request, string $email, string $password, bool $remember): void
    {
        $key = Str::lower($email).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            event(new Lockout($request));
            throw ValidationException::withMessages(['email' => 'Too many attempts. Try again in '.RateLimiter::availableIn($key).' seconds.']);
        }
        if (! Auth::attempt(['email' => Str::lower($email), 'password' => $password, 'is_active' => true], $remember)) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => 'These credentials do not match an active account.']);
        }
        RateLimiter::clear($key);
        $request->session()->regenerate();
        $this->users->recordLogin(Auth::user());
    }

    /** Landing page per role: callers go straight to the CRM Today screen. */
    public function homeFor(\App\Models\User $user): string
    {
        return $user->hasPermission('dashboard.view') ? route('dashboard') : route('crm.today');
    }

    public function logout(Request $request): void
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    public function sendResetLink(string $email): string
    {
        return Password::sendResetLink(['email' => Str::lower($email)]);
    }

    public function reset(array $data): string
    {
        return Password::reset($data, function ($user, string $password) {
            $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
        });
    }
}
