<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Queue Connection Name
    |--------------------------------------------------------------------------
    |
    | Spinx supports "database", "redis", and "sync" queue connections out of the box.
    |
    */
    'default' => env('QUEUE_CONNECTION', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Queue Connections
    |--------------------------------------------------------------------------
    |
    | Here you may configure the connection information for each server that
    | is used by your application.
    |
    */
    'connections' => [
        'sync' => [
            'driver' => 'sync',
        ],

        'database' => [
            'driver' => 'database',
            'table'  => 'spinx_jobs',
            'queue'  => 'default',
        ],

        'redis' => [
            'driver'     => 'redis',
            'connection' => 'queue',
            'queue'      => 'default',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Failed Job Log Database Table
    |--------------------------------------------------------------------------
    */
    'failed' => [
        'table' => 'spinx_failed_jobs',
    ],
];
