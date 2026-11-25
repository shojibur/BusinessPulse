<?php

namespace App\Http\Middleware;

use App\Support\Navigation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Returns 403 when a user opens a navigation section their role does not see. The navigation
 * list is display only; this middleware (and policies) are the enforcement.
 */
class EnsureSectionAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user !== null && Navigation::canAccessCurrentSection($request, $user), 403);

        return $next($request);
    }
}
