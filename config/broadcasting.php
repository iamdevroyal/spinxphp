<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Broadcast Driver
    |--------------------------------------------------------------------------
    |
    | Supported drivers: "pusher", "log", "null"
    |
    */
    'default' => env('BROADCAST_DRIVER', 'log'),

    /*
    |--------------------------------------------------------------------------
    | Broadcast Connections
    |--------------------------------------------------------------------------
    |
    | "pusher" driver works natively with Pusher Cloud, Soketi, or Laravel Reverb.
    | "log" driver outputs broadcast events to your log file for local dev.
    | "null" driver discards events during unit/integration tests.
    |
    */
    'connections' => [
        'pusher' => [
            'driver'  => 'pusher',
            'key'     => env('PUSHER_APP_KEY', ''),
            'secret'  => env('PUSHER_APP_SECRET', ''),
            'app_id'  => env('PUSHER_APP_ID', ''),
            'options' => [
                'host'    => env('PUSHER_HOST', 'api.pusherapp.com'),
                'port'    => (int) env('PUSHER_PORT', 443),
                'scheme'  => env('PUSHER_SCHEME', 'https'),
                'cluster' => env('PUSHER_APP_CLUSTER', 'mt1'),
            ],
        ],

        'log' => [
            'driver' => 'log',
        ],

        'null' => [
            'driver' => 'null',
        ],
    ],
];
