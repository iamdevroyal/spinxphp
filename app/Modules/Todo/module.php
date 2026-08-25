<?php

declare(strict_types=1);

use App\Modules\Todo\Infrastructure\Http\Controllers\TodoCreateController;
use App\Modules\Todo\Infrastructure\Http\Controllers\TodoListController;
use App\Modules\Todo\Infrastructure\Http\Controllers\TodoToggleController;
use Spinx\Http\Middleware\CsrfMiddleware;
use Spinx\Routing\AliasRegistry;
use Spinx\Routing\Route;
use Spinx\Routing\RouteBuilder;

/**
 * Todo module — raw HTML reference module (build spec §12, step 10).
 *
 * Proves Spinx works with zero frontend JavaScript framework. The views use
 * only @if/@foreach/{{ }}, no @island anywhere. Compare with the Health
 * module (Vue) and examples/react-frontend (React).
 *
 * Also the reference example for CsrfMiddleware. CSRF is attached to all
 * three routes including the GET index route — the cookie must be set on
 * SOME response before the browser has a token to submit back on the next
 * POST, and the index page is where the @csrf-rendered form field gets its
 * value from in the first place.
 *
 * Migrated to the fluent Route DSL (Phase 2.4).
 */
return [
    'controllers' => static function (AliasRegistry $r): void {
        $r->registerController('todo_list',   TodoListController::class);
        $r->registerController('todo_create', TodoCreateController::class);
        $r->registerController('todo_toggle', TodoToggleController::class);
    },

    'middlewares' => static function (AliasRegistry $r): void {
        $r->registerMiddleware('csrf', CsrfMiddleware::class);
    },

    'routes' => static function (RouteBuilder $routes): void {
        Route::get(['todo.index', '/todos'])
            ->middleware(['csrf'])
            ->controller('todo_list');

        Route::post(['todo.create', '/todos'])
            ->middleware(['csrf'])
            ->controller('todo_create');

        Route::post(['todo.toggle', '/todos/{id}/toggle'])
            ->middleware(['csrf'])
            ->controller('todo_toggle');
    },
];
