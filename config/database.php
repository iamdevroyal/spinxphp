<?php

declare(strict_types=1);

/**
 * Read by Spinx\Database\Connection\ConnectionManagerFactory via the
 * config() helper — see that class for how "driver" here (the database
 * driver) relates to spinx.json's "driver" key (the RUNTIME driver,
 * RoadRunner vs Swoole — a different, easily confused thing with a
 * similar name).
 */
return [
    'driver' => env('DB_DRIVER', 'pdo_sqlite'),

    // Used when driver is pdo_sqlite (the zero-config default):
    'path' => env('DB_PATH', 'storage/database.sqlite'),

    // Used when driver is pdo_mysql, pdo_pgsql, etc.:
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => env('DB_PORT'),
    'database' => env('DB_DATABASE'),
    'username' => env('DB_USERNAME'),
    'password' => env('DB_PASSWORD'),
];
