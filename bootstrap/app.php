<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Support\Navigation;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
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

            // Exceptions such as unmatched routes bypass the web middleware, so share the shell props here.
            Inertia::share([
                'name' => config('app.name'),
                'navigation' => Navigation::items($request),
            ]);

            return Inertia::render('Error', ['status' => $status])
                ->toResponse($request)
                ->setStatusCode($status);
        });
    })->create();
