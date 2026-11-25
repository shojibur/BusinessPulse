<?php

/*
|--------------------------------------------------------------------------
| Sales Import
|--------------------------------------------------------------------------
|
| The single definition of the CSV format. The template download, the Settings
| template tab and header validation all read this file. The limits are demonstration
| defaults (docs/stage-1-scope.md), not confirmed client requirements.
|
*/

return [

    // Column order of the template; uploads may use any column order.
    'columns' => [
        'sales_date' => [
            'required' => true,
            'type' => 'date',
            'rule' => 'Calendar date YYYY-MM-DD, not after today, not before 2000-01-01',
            'example' => '2025-03-03',
        ],
        'branch_code' => [
            'required' => true,
            'type' => 'text',
            'rule' => '1 to 20 letters, digits, hyphen or underscore. Stored in upper case. Must be an expected branch.',
            'regex' => '/^[A-Za-z0-9_-]{1,20}$/',
            'example' => 'KL',
        ],
        'revenue' => [
            'required' => true,
            'type' => 'decimal',
            'rule' => 'Up to 12 digits and 2 decimals, zero allowed, no sign, thousands separator or currency symbol',
            'regex' => '/^[0-9]{1,12}(\.[0-9]{1,2})?$/',
            'example' => '4520.50',
        ],
        'target_revenue' => [
            'required' => true,
            'type' => 'decimal',
            'rule' => 'Same rule as revenue',
            'regex' => '/^[0-9]{1,12}(\.[0-9]{1,2})?$/',
            'example' => '5000.00',
        ],
        'order_count' => [
            'required' => false,
            'type' => 'integer',
            'rule' => 'Whole number up to 9 digits. Leave empty when unknown.',
            'regex' => '/^[0-9]{1,9}$/',
            'example' => '37',
        ],
    ],

    'max_file_bytes' => 5 * 1024 * 1024,

    'max_rows' => 10000,

    'allowed_mime_types' => ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'],

    'minimum_date' => '2000-01-01',

    // Uploads per minute per user.
    'uploads_per_minute' => 10,

    // Rows written per statement inside the processing transaction.
    'write_chunk_size' => 1000,

    'errors_per_page' => 50,

    'imports_per_page' => 25,

    // Disk folder (private local disk) for uploaded files.
    'storage_directory' => 'imports',
];
