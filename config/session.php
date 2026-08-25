<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Session Driver
    |--------------------------------------------------------------------------
    |
    | Supported: "file", "database"
    |
    */
    'driver' => env('SESSION_DRIVER', 'file'),

    /*
    |--------------------------------------------------------------------------
    | Session Lifetime (Minutes)
    |--------------------------------------------------------------------------
    |
    | The number of minutes the session should remain idle before expiring.
    |
    */
    'lifetime' => (int) env('SESSION_LIFETIME', 120),

    /*
    |--------------------------------------------------------------------------
    | Session File Storage Path
    |--------------------------------------------------------------------------
    |
    | When using the "file" driver, sessions are stored as JSON files here.
    |
    */
    'path' => storage_path('sessions'),

    /*
    |--------------------------------------------------------------------------
    | Cookie Configuration
    |--------------------------------------------------------------------------
    */
    'secure' => (bool) env('SESSION_SECURE_COOKIE', false),
    'same_site' => env('SESSION_SAME_SITE', 'Lax'),
];
