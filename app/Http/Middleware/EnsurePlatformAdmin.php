<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the /platform panel (S10): only a signed-in, active platform admin passes. Company users never do, because
 * they are authenticated on the `web` guard, not the `platform` guard.
 */
class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('platform');
        $admin = $guard->user();

        if ($admin === null) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Unauthenticated.'], 401)
                : redirect()->route('platform.login');
        }

        if (! $admin->is_active) {
            $guard->logout();
            $request->session()->regenerateToken();

            return redirect()->route('platform.login')->withErrors(['email' => 'Your account is inactive.']);
        }

        // `$request->user()` means the platform admin for this request only; the default guard is never changed, so
        // nothing leaks into later requests of a long-running process.
        $request->setUserResolver(fn () => $admin);

        return $next($request);
    }
}
