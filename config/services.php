<?php

declare(strict_types=1);

/**
 * Credentials for external services/APIs — one
 * array per service, values pulled from .env with sane defaults where a
 * default makes sense (secrets never get a default, obviously).
 *
 * Access via config('services.paystack.secret_key'), or inject
 * Spinx\Http\HttpClient and call one of the pre-wired helper methods —
 * see docs/external-services.md for the full pattern including a
 * working Paystack example.
 *
 * Add your own service here as you integrate it — nothing else needs to
 * change for a new array key to become available via config().
 */
return [
    'paystack' => [
        'secret_key' => env('PAYSTACK_SECRET_KEY'),
        'public_key' => env('PAYSTACK_PUBLIC_KEY'),
        'base_url' => env('PAYSTACK_BASE_URL', 'https://api.paystack.co'),
    ],

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'base_url' => env('STRIPE_BASE_URL', 'https://api.stripe.com/v1'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
        'base_url' => env('RESEND_BASE_URL', 'https://api.resend.com'),
    ],

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'base_url' => env('MAILGUN_BASE_URL', 'https://api.mailgun.net/v3'),
    ],
];
