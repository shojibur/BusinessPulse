<?php

namespace App\Providers;

use App\Models\Brief;
use App\Models\Department;
use App\Models\Import;
use App\Models\Issue;
use App\Policies\IssuePolicy;
use App\Services\CurrentCompany;
use App\Support\BusinessClock;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(BusinessClock::class);
        $this->app->scoped(CurrentCompany::class);
    }

    /**
     * Trust the configured proxies (TRUSTED_PROXIES) so https, host and client address come from X-Forwarded-*.
     */
    public function configureTrustedProxies(): void
    {
        $value = config('app.trusted_proxies');

        if (is_string($value) && trim($value) !== '') {
            TrustProxies::at(trim($value) === '*' ? '*' : array_values(array_filter(array_map('trim', explode(',', $value)))));
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureTrustedProxies();
        $this->configureDefaults();
        $this->configureRouteBindings();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Department-scoped route binding: an out-of-scope record is a 404 and its existence is not revealed.
     * Later specs bind their own department-scoped models the same way with `Model::visibleTo($user)`.
     */
    protected function configureRouteBindings(): void
    {
        Route::bind('department', function (string $value): Department {
            $user = request()->user();
            abort_if($user === null, 404);

            return Department::visibleTo($user)->findOrFail($value);
        });

        Route::bind('import', function (string $value): Import {
            $user = request()->user();
            abort_if($user === null, 404);

            return Import::visibleTo($user)->findOrFail($value);
        });

        // Refresh intelligence authorizes against the department through IssuePolicy::refresh.
        Gate::define('refresh-intelligence', [IssuePolicy::class, 'refresh']);

        Route::bind('issue', function (string $value): Issue {
            $user = request()->user();
            abort_if($user === null, 404);

            return Issue::visibleTo($user)->findOrFail($value);
        });

        // A brief outside the user's company or department answers 404 before any policy runs.
        Route::bind('brief', function (string $value): Brief {
            $user = request()->user();
            abort_if($user === null, 404);

            return Brief::visibleTo($user)->findOrFail($value);
        });

        RateLimiter::for('meetings-upload', fn (Request $request) => Limit::perMinute(10)->by('mu:'.$request->user()?->id));
        RateLimiter::for('meetings-actions', fn (Request $request) => Limit::perMinute(20)->by('ma:'.$request->user()?->id));
        RateLimiter::for('meetings-media', fn (Request $request) => Limit::perMinute(120)->by('mm:'.$request->user()?->id));

        // One shared counter per user for edit, approve and reject (and, later, reassign).
        RateLimiter::for('task-review', fn (Request $request) => Limit::perMinute(20)->by('tr:'.$request->user()?->id));
        RateLimiter::for('task-status', fn (Request $request) => Limit::perMinute(60)->by('ts:'.$request->user()?->id));
        RateLimiter::for('task-comment', fn (Request $request) => Limit::perMinute(30)->by('tc:'.$request->user()?->id));

        RateLimiter::for('ai-requests', fn (Request $request) => Limit::perMinute(6)->by('ai:'.$request->user()?->id));
        RateLimiter::for('audio-stream', fn (Request $request) => Limit::perMinute(60)->by('audio:'.$request->user()?->id));

        RateLimiter::for('imports-upload', fn (Request $request) => Limit::perMinute((int) config('imports.uploads_per_minute'))
            ->by((string) $request->user()?->id));
    }
}
