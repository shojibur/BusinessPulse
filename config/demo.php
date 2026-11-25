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

    /*
    |--------------------------------------------------------------------------
    | Allow Demo Commands in Production
    |--------------------------------------------------------------------------
    |
    | demo:seed and demo:reset refuse to run when APP_ENV is production unless
    | this is explicitly true (a dedicated demo server only). Default false.
    |
    */

    'allow_production' => (bool) env('DEMO_ALLOW_PRODUCTION_DEMO', false),

];
