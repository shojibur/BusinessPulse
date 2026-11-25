<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Business Timezone
    |--------------------------------------------------------------------------
    |
    | Default timezone of the BusinessClock. Asia/Kuala_Lumpur is a demo default,
    | not a confirmed client requirement. From S02 the company timezone is passed
    | to the BusinessClock explicitly.
    |
    */

    'timezone' => env('BUSINESS_TIMEZONE', 'Asia/Kuala_Lumpur'),

];
