<?php

declare(strict_types=1);

use App\Modules\Auth\Application\Services\AuthService;
use App\Modules\Auth\Domain\Repositories\UserRepositoryInterface;
use App\Modules\Auth\Infrastructure\Http\Controllers\AuthController;
use App\Modules\Auth\Infrastructure\Repositories\UserRepository;
use Spinx\Auth\Middleware\AuthMiddleware;
use Spinx\Auth\Middleware\GuestMiddleware;
use Spinx\Http\Middleware\CsrfMiddleware;
use Spinx\Routing\AliasRegistry;
use Spinx\Routing\Route;
use Spinx\Routing\RouteBuilder;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Auth module — Reference authentication module for Spinx.
 *
 * Demonstrates Strict Domain-Driven Design (DDD) & Multi-Action Controller Routing:
 *  - Domain: User entity & UserRepositoryInterface
 *  - Application: AuthService encapsulating registration, password hashing & session auth
 *  - Infrastructure: UserRepository DB persistence & unified AuthController
 *  - CSRF protection via CsrfMiddleware & @csrf directive
 *  - AuthMiddleware and GuestMiddleware session guards
 */
return [
    'services' => static function (ContainerBuilder $c, string $moduleDir): void {
        $c->register(UserRepository::class)
            ->setAutowired(true)
            ->setPublic(true);

        $c->setAlias(UserRepositoryInterface::class, UserRepository::class)
            ->setPublic(true);

        $c->register(AuthService::class)
            ->setAutowired(true)
            ->setPublic(true);

        $c->register(AuthController::class)
            ->setAutowired(true)
            ->setPublic(true);
    },

    'controllers' => static function (AliasRegistry $r): void {
        $r->registerController('auth', AuthController::class);
    },

    'middlewares' => static function (AliasRegistry $r): void {
        $r->registerMiddleware('auth',  AuthMiddleware::class);
        $r->registerMiddleware('guest', GuestMiddleware::class);
        $r->registerMiddleware('csrf',  CsrfMiddleware::class);
    },

    'routes' => static function (RouteBuilder $routes): void {
        // Guest Routes (Login & Register)
        Route::get(['auth.login', '/login'])->middleware(['guest', 'csrf'])->controller('auth@showLogin');

        Route::post(['auth.login.submit', '/login'])->middleware(['guest', 'csrf'])->controller('auth@login');

        Route::get(['auth.register', '/register'])->middleware(['guest', 'csrf'])->controller('auth@showRegister');

        Route::post(['auth.register.submit', '/register'])->middleware(['guest', 'csrf'])->controller('auth@register');

        // Authenticated Protected Routes
        Route::post(['auth.logout', '/logout'])->middleware(['auth', 'csrf'])->controller('auth@logout');

        Route::get(['auth.dashboard', '/dashboard'])->middleware(['auth', 'csrf'])->controller('auth@dashboard');
    },
];
