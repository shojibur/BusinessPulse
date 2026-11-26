<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ends the session of a deactivated user on their very next request. Runs after `auth`.
 */
class EnsureUserIsActive
{
    public const MESSAGE = 'Your account is inactive.';

    public const SUSPENDED_MESSAGE = "This company's account is suspended.";

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $suspended = $user !== null && $user->company?->isSuspended();

        if ($user !== null && (! $user->is_active || $suspended)) {
            $message = $suspended ? self::SUSPENDED_MESSAGE : self::MESSAGE;
            Auth::guard()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 401);
            }

            return redirect()->route('login')->withErrors(['email' => $message]);
        }

        return $next($request);
    }
}
