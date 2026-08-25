<?php

declare(strict_types=1);

use App\Modules\Health\Infrastructure\Http\Controllers\HealthController;
use App\Modules\Health\Infrastructure\Http\Middleware\SecurityHeadersMiddleware;
use Spinx\Http\Middleware\CorsMiddleware;
use Spinx\Http\Middleware\RateLimitMiddleware;
use Spinx\Routing\AliasRegistry;
use Spinx\Routing\Route;
use Spinx\Routing\RouteBuilder;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Health module — Framework reference and status module.
 */
return [
    'services' => static function (ContainerBuilder $c, string $moduleDir): void {
        $c->register(HealthController::class)
            ->setAutowired(true)
            ->setPublic(true);
    },

    'controllers' => static function (AliasRegistry $r): void {
        $r->registerController('health', HealthController::class);
    },

    'middlewares' => static function (AliasRegistry $r): void {
        $r->registerMiddleware('rate_limit',       RateLimitMiddleware::class);
        $r->registerMiddleware('cors',             CorsMiddleware::class);
        $r->registerMiddleware('security_headers', SecurityHeadersMiddleware::class);
    },

    'routes' => static function (RouteBuilder $routes): void {
        Route::get(['health.check', '/health'])
            ->middleware(['rate_limit', 'cors', 'security_headers'])
            ->controller('health@check');

        Route::get(['welcome', '/'])
            ->controller('health@welcome');
    },
];
