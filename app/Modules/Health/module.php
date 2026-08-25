<?php

declare(strict_types=1);

use App\Modules\Health\Infrastructure\Http\Controllers\HealthCheckController;
use App\Modules\Health\Infrastructure\Http\Controllers\WelcomeController;
use App\Modules\Health\Infrastructure\Http\Middleware\SecurityHeadersMiddleware;
use Spinx\Http\Middleware\CorsMiddleware;
use Spinx\Http\Middleware\RateLimitMiddleware;
use Spinx\Routing\AliasRegistry;
use Spinx\Routing\Route;
use Spinx\Routing\RouteBuilder;

/**
 * Health module — framework reference implementation.
 *
 * Migrated to the fluent Route DSL (Phase 2.4). Controllers and middlewares
 * are registered via 'controllers'/'middlewares' closures so the alias
 * system handles both DI registration and route resolution in one place.
 *
 * Route comments preserved from the original: RateLimit runs outermost
 * (rejects before CORS headers are computed for an over-limit caller),
 * then CORS, then the security headers get added closest to the response.
 */
return [
    'controllers' => static function (AliasRegistry $r): void {
        $r->registerController('health_check', HealthCheckController::class);
        $r->registerController('welcome',      WelcomeController::class);
    },

    'middlewares' => static function (AliasRegistry $r): void {
        $r->registerMiddleware('rate_limit',       RateLimitMiddleware::class);
        $r->registerMiddleware('cors',             CorsMiddleware::class);
        $r->registerMiddleware('security_headers', SecurityHeadersMiddleware::class);
    },

    'routes' => static function (RouteBuilder $routes): void {
        Route::get(['health.check', '/health'])
            ->middleware(['rate_limit', 'cors', 'security_headers'])
            ->controller('health_check');

        Route::get(['welcome', '/'])
            ->controller('welcome');
    },
];
