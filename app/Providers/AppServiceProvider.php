<?php

namespace App\Providers;

use App\Models\Department;
use App\Services\CurrentCompany;
use App\Support\BusinessClock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
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
     * Bootstrap any application services.
     */
    public function boot(): void
    {
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
    }
}
