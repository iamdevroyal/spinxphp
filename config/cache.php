<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Cache Store
    |--------------------------------------------------------------------------
    |
    | This option controls the default cache connection that gets used while
    | using this caching library. Supported: "file", "array", "redis"
    |
    */
    'default' => env('CACHE_DRIVER', 'file'),

    /*
    |--------------------------------------------------------------------------
    | Cache Stores
    |--------------------------------------------------------------------------
    |
    | Here you may define all of the cache "stores" for your application as
    | well as their drivers.
    |
    */
    'stores' => [
        'file' => [
            'driver' => 'file',
            'path'   => storage_path('cache/data'),
        ],

        'array' => [
            'driver' => 'array',
        ],

        'redis' => [
            'driver'   => 'redis',
            'host'     => env('REDIS_HOST', '127.0.0.1'),
            'port'     => (int) env('REDIS_PORT', 6379),
            'password' => env('REDIS_PASSWORD', null),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Key Prefix
    |--------------------------------------------------------------------------
    |
    | When utilizing a RAM based store such as Redis, there might be other
    | applications utilizing the same cache. So, we'll specify a value to
    | get prefixed to all our keys so we can avoid collisions.
    |
    */
    'prefix' => env('CACHE_PREFIX', 'spinx_cache:'),
];
