<?php

declare(strict_types=1);

use App\Modules\Auth\Infrastructure\Http\Controllers\DashboardController;
use App\Modules\Auth\Infrastructure\Http\Controllers\LoginShowController;
use App\Modules\Auth\Infrastructure\Http\Controllers\LoginSubmitController;
use App\Modules\Auth\Infrastructure\Http\Controllers\LogoutController;
use App\Modules\Auth\Infrastructure\Http\Controllers\RegisterShowController;
use App\Modules\Auth\Infrastructure\Http\Controllers\RegisterSubmitController;
use Spinx\Auth\Middleware\AuthMiddleware;
use Spinx\Auth\Middleware\GuestMiddleware;
use Spinx\Http\Middleware\CsrfMiddleware;
use Spinx\Routing\AliasRegistry;
use Spinx\Routing\Route;
use Spinx\Routing\RouteBuilder;

/**
 * Auth module — Reference authentication module for Spinx.
 *
 * Demonstrates:
 *  - Single-responsibility DDD controllers
 *  - Form CSRF protection via CsrfMiddleware & @csrf directive
 *  - AuthMiddleware and GuestMiddleware session guards
 *  - Passwords hashed via Argon2id (Spinx\Auth\Hash)
 *  - Active Record User model persistence
 */
return [
    'controllers' => static function (AliasRegistry $r): void {
        $r->registerController('auth_login_show',       LoginShowController::class);
        $r->registerController('auth_login_submit',     LoginSubmitController::class);
        $r->registerController('auth_register_show',    RegisterShowController::class);
        $r->registerController('auth_register_submit',  RegisterSubmitController::class);
        $r->registerController('auth_logout',           LogoutController::class);
        $r->registerController('auth_dashboard',        DashboardController::class);
    },

    'middlewares' => static function (AliasRegistry $r): void {
        $r->registerMiddleware('auth',  AuthMiddleware::class);
        $r->registerMiddleware('guest', GuestMiddleware::class);
        $r->registerMiddleware('csrf',  CsrfMiddleware::class);
    },

    'routes' => static function (RouteBuilder $routes): void {
        // Guest Routes (Login & Register)
        Route::get(['auth.login', '/login'])
            ->middleware(['guest', 'csrf'])
            ->controller('auth_login_show');

        Route::post(['auth.login.submit', '/login'])
            ->middleware(['guest', 'csrf'])
            ->controller('auth_login_submit');

        Route::get(['auth.register', '/register'])
            ->middleware(['guest', 'csrf'])
            ->controller('auth_register_show');

        Route::post(['auth.register.submit', '/register'])
            ->middleware(['guest', 'csrf'])
            ->controller('auth_register_submit');

        // Authenticated Protected Routes
        Route::post(['auth.logout', '/logout'])
            ->middleware(['auth', 'csrf'])
            ->controller('auth_logout');

        Route::get(['auth.dashboard', '/dashboard'])
            ->middleware(['auth', 'csrf'])
            ->controller('auth_dashboard');
    },
];
