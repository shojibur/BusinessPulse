<?php

use App\Http\Middleware\EnsureSectionAccess;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Signed-in users who open the login page go to Today.
        $middleware->redirectUsersTo(fn ($request) => route($request->user()?->isStaff() ? 'tasks.mine' : 'today'));

        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'section.access' => EnsureSectionAccess::class,
        ]);

        // Check the account and the role before route model binding runs, so a deactivated user or a
        // wrong role is rejected before any record is resolved.
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: EnsureSectionAccess::class);
        $middleware->prependToPriorityList(before: EnsureSectionAccess::class, prepend: EnsureUserIsActive::class);

        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Log the request path and method with every reported exception, never bodies or headers.
        $exceptions->context(fn () => [
            'path' => request()->path(),
            'method' => request()->method(),
        ]);

        // Render HTML failures as Inertia error pages inside the app layout. With APP_DEBUG=true,
        // 500 keeps Laravel's debug page for developers.
        $exceptions->respond(function (Response $response, Throwable $e, Request $request): Response {
            $status = $response->getStatusCode();

            if ($request->expectsJson()
                || ! in_array($status, [403, 404, 419, 500, 503], true)
                || ($status === 500 && config('app.debug'))) {
                return $response;
            }

            // Errors raised before the web middleware ran share the guest props here. When the middleware did
            // run, its shared props (signed-in user, navigation) are kept.
            if (Inertia::getShared('auth') === null) {
                Inertia::share([
                    'name' => config('app.name'),
                    'auth' => ['user' => null, 'role' => null],
                    'company' => null,
                    'navigation' => [],
                ]);
            }

            return Inertia::render('Error', ['status' => $status])
                ->toResponse($request)
                ->setStatusCode($status);
        });
    })->create();
