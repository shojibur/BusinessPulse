<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Retention: delete recordings older than each company's retention period (S06 requirement 15).
Schedule::command('recordings:purge')->dailyAt('02:00')->withoutOverlapping()->onOneServer();
