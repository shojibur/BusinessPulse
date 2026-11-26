<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public const FAILED_MESSAGE = 'These credentials do not match our records.';

    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 60;

    public function create(): Response
    {
        return Inertia::render('auth/Login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $key = $request->throttleKey();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($key);
            Log::warning('Login throttled', ['ip' => $request->ip()]);

            throw ValidationException::withMessages([
                'email' => "Too many login attempts. Please try again in {$seconds} seconds.",
            ]);
        }

        // Email is stored lowercase, so lowercasing the input makes the lookup case-insensitive.
        // Requiring is_active makes an inactive user indistinguishable from a wrong password.
        $authenticated = Auth::attempt([
            'email' => mb_strtolower(trim($request->string('email')->toString())),
            'password' => $request->string('password')->toString(),
            'is_active' => true,
        ]);

        if (! $authenticated) {
            RateLimiter::hit($key, self::DECAY_SECONDS);
            Log::warning('Login failed', ['ip' => $request->ip()]);

            throw ValidationException::withMessages(['email' => self::FAILED_MESSAGE]);
        }

        // A suspended company cannot sign in (S10). Only reached with a valid password.
        if ($request->user()->company?->isSuspended()) {
            Auth::guard()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages(['email' => EnsureUserIsActive::SUSPENDED_MESSAGE]);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        // Staff land on their own tasks (S07); Owner and Manager on Today.
        return redirect()->route($request->user()->isStaff() ? 'tasks.mine' : 'today');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard()->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
