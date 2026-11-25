<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/today')->name('home');

// S01 placeholder pages: one route per primary navigation item. Later specs replace
// each with a controller. Every visitor can open them until S02 adds authentication.
Route::inertia('today', 'dashboard/Index')->name('today');
Route::inertia('issues', 'issues/Index')->name('issues.index');
Route::inertia('imports', 'imports/Index')->name('imports.index');
Route::inertia('briefs', 'briefs/Index')->name('briefs.index');
Route::inertia('meetings', 'meetings/Index')->name('meetings.index');
Route::inertia('tasks', 'tasks/Index')->name('tasks.index');
Route::inertia('outcomes', 'outcomes/Index')->name('outcomes.index');
Route::inertia('settings', 'settings/Index')->name('settings.index');
