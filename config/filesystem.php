<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Supported disks: "local", "s3"
    |
    */
    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | "local" stores files on the local filesystem in storage/app.
    | "s3" works with AWS S3, Cloudflare R2, MinIO, or DigitalOcean Spaces.
    |
    */
    'disks' => [
        'local' => [
            'driver' => 'local',
            'root'   => storage_path('app'),
            'url'    => env('APP_URL', 'http://localhost:8000') . '/storage',
        ],

        'public' => [
            'driver' => 'local',
            'root'   => storage_path('app/public'),
            'url'    => env('APP_URL', 'http://localhost:8000') . '/storage',
        ],

        's3' => [
            'driver'   => 's3',
            'key'      => env('AWS_ACCESS_KEY_ID', ''),
            'secret'   => env('AWS_SECRET_ACCESS_KEY', ''),
            'region'   => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'bucket'   => env('AWS_BUCKET', ''),
            'endpoint' => env('AWS_ENDPOINT', null),
            'url'      => env('AWS_URL', null),
        ],
    ],
];
