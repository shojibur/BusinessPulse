<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Demo Mode
    |--------------------------------------------------------------------------
    |
    | When true, the BusinessClock honors the demo clock file and the guarded
    | demo commands may run. Must stay false outside dedicated demo environments.
    |
    */

    'mode' => (bool) env('DEMO_MODE', false),

    /*
    |--------------------------------------------------------------------------
    | Demo Clock File
    |--------------------------------------------------------------------------
    |
    | Path on the private (local) disk holding the demo date-time as
    | "YYYY-MM-DD HH:MM" in the business timezone. Written by demo:set-date.
    |
    */

    'clock_file' => 'demo/fixed-datetime',

    /*
    |--------------------------------------------------------------------------
    | Demo User Password
    |--------------------------------------------------------------------------
    |
    | Used by the demo seeder in a later specification. Never exposed to the
    | frontend. Left empty by default.
    |
    */

    'password' => env('DEMO_PASSWORD'),

];
