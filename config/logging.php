<?php

declare(strict_types=1);

/**
 * Spinx Logging Configuration
 *
 * Defines the default logging channel and driver settings for application logs.
 */
return [
    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    |
    | This option defines the default log channel that gets used when writing
    | messages to the logs. The name specified in this option should match
    | one of the channels defined in the "channels" configuration array below.
    |
    */
    'default' => env('LOG_CHANNEL', 'daily'),

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    |
    | Here you may configure the log channels for your application. Spinx
    | includes drivers for daily log files, single continuous files,
    | stderr/stdout streams, aggregated stacks, and null discards.
    |
    | Available Drivers: "daily", "single", "stderr", "stdout", "stack", "null"
    |
    */
    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => ['daily'],
            'level' => env('LOG_LEVEL', 'debug'),
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/spinx.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => (int) env('LOG_DAILY_DAYS', 14),
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/spinx.log'),
            'level' => env('LOG_LEVEL', 'debug'),
        ],

        'stderr' => [
            'driver' => 'stderr',
            'level' => env('LOG_LEVEL', 'debug'),
        ],

        'stdout' => [
            'driver' => 'stdout',
            'level' => env('LOG_LEVEL', 'debug'),
        ],

        'null' => [
            'driver' => 'null',
        ],
    ],
];
