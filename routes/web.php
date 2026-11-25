<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/today')->name('home');

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

// Every application route requires a signed-in, active user. `section.access` returns 403 for navigation
// sections the role does not see; policies authorize each record.
Route::middleware(['auth', 'active', 'section.access'])->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    // S01 placeholder pages: later specs replace each with a controller.
    Route::inertia('today', 'dashboard/Index')->name('today');
    Route::inertia('issues', 'issues/Index')->name('issues.index');
    Route::inertia('imports', 'imports/Index')->name('imports.index');
    Route::inertia('briefs', 'briefs/Index')->name('briefs.index');
    Route::inertia('meetings', 'meetings/Index')->name('meetings.index');
    Route::inertia('tasks', 'tasks/Index')->name('tasks.index');
    Route::inertia('outcomes', 'outcomes/Index')->name('outcomes.index');

    Route::redirect('settings', '/settings/company')->name('settings.index');
    Route::get('settings/company', [CompanyController::class, 'edit'])->name('settings.company');
    Route::get('settings/staff', [UserController::class, 'index'])->name('settings.staff');
    Route::get('settings/department/{department?}', [DepartmentController::class, 'show'])->name('settings.department');
});

// Unmatched URLs (any method) run through the web middleware so the 404 page knows who is signed in.
// Removed starter-kit routes such as /register therefore answer 404, never 405.
Route::match(['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE'], '{fallbackPlaceholder}', fn () => abort(404))
    ->where('fallbackPlaceholder', '.*')
    ->fallback();
