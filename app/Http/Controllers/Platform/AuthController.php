<?php

namespace App\Http\Controllers\Platform;

use App\Actions\Platform\PlatformAudit;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Sign-in for platform admins (S10). Throttled; a wrong email and a wrong password look the same. */
class AuthController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 60;

    public function create(): Response
    {
        return Inertia::render('platform/Login');
    }

    public function store(LoginRequest $request, PlatformAudit $audit): RedirectResponse
    {
        $key = 'platform|'.$request->throttleKey();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($key);
            Log::warning('Platform login throttled', ['ip' => $request->ip()]);

            throw ValidationException::withMessages(['email' => "Too many login attempts. Please try again in {$seconds} seconds."]);
        }

        $guard = Auth::guard('platform');

        if (! $guard->attempt([
            'email' => mb_strtolower(trim($request->string('email')->toString())),
            'password' => $request->string('password')->toString(),
            'is_active' => true,
        ])) {
            RateLimiter::hit($key, self::DECAY_SECONDS);
            Log::warning('Platform login failed', ['ip' => $request->ip()]);

            throw ValidationException::withMessages(['email' => 'These credentials do not match our records.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        $audit->record($guard->user(), 'platform.signed_in', null, ['ip' => $request->ip()]);

        return redirect()->route('platform.dashboard');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('platform')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('platform.login');
    }
}
