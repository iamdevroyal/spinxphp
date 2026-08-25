<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default User Model / Provider
    |--------------------------------------------------------------------------
    |
    | The Eloquent model class representing application users.
    |
    */
    'model' => env('AUTH_MODEL', 'App\\Modules\\Auth\\Infrastructure\\Persistence\\Models\\User'),

    'primary_key' => 'id',
    'password_field' => 'password',

    /*
    |--------------------------------------------------------------------------
    | Unauthenticated Behavior
    |--------------------------------------------------------------------------
    |
    | When an unauthenticated user hits an auth-protected route:
    | "redirect" (browser requests) or "json" (401 response).
    |
    */
    'unauthenticated' => 'redirect',
    'redirect_to' => '/login',
    'home' => '/dashboard',
];
