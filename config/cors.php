<?php

declare(strict_types=1);

/** Read by Spinx\Http\Middleware\CorsMiddleware. */
return [
    // '*' allows any origin — fine for local dev, never use it in
    // production for endpoints that accept cookies/auth headers (the
    // CORS spec forbids credentialed requests from a wildcard origin
    // anyway; CorsMiddleware reflects the request's actual Origin
    // instead of literally sending '*' when credentials are allowed).
    'allowed_origins' => array_filter(explode(',', (string) env('CORS_ALLOWED_ORIGINS', '*'))),

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With'],

    'allow_credentials' => env('CORS_ALLOW_CREDENTIALS', false),

    'max_age' => env('CORS_MAX_AGE', 86400),
];
