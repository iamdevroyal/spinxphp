<?php

declare(strict_types=1);

/**
 * Read by Spinx\Mail\Mailer to build a Symfony Mailer DSN. "driver" here
 * maps to a transport: smtp uses host/port/username/password directly;
 * ses/mailgun/resend are API-based and only need their credential below
 * (see docs/mail.md for the exact DSN Spinx builds for each).
 */
return [
    'driver' => env('MAIL_DRIVER', 'smtp'),

    'host' => env('MAIL_HOST', 'localhost'),
    'port' => env('MAIL_PORT', 1025),
    'username' => env('MAIL_USERNAME'),
    'password' => env('MAIL_PASSWORD'),
    'encryption' => env('MAIL_ENCRYPTION'),

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name' => env('MAIL_FROM_NAME', env('APP_NAME', 'Spinx App')),
    ],

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],
];
