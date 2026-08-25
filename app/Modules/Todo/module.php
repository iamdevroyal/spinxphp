<?php

declare(strict_types=1);

use App\Modules\Todo\Application\Services\TodoService;
use App\Modules\Todo\Domain\Repositories\TodoRepositoryInterface;
use App\Modules\Todo\Infrastructure\Http\Controllers\TodoController;
use App\Modules\Todo\Infrastructure\Repositories\TodoRepository;
use Spinx\Http\Middleware\CsrfMiddleware;
use Spinx\Routing\AliasRegistry;
use Spinx\Routing\Route;
use Spinx\Routing\RouteBuilder;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Todo module — Strict DDD Reference Module proving Spinx works with zero frontend JS.
 *
 * Demonstrates:
 *  - Domain: Todo entity & TodoRepositoryInterface
 *  - Application: TodoService
 *  - Infrastructure: TodoRepository & unified TodoController
 *  - Multi-action controller routing (@index, @store, @toggle)
 *  - Session-backed CSRF protection
 */
return [
    'services' => static function (ContainerBuilder $c, string $moduleDir): void {
        $c->register(TodoRepository::class)
            ->setAutowired(true)
            ->setPublic(true);

        $c->setAlias(TodoRepositoryInterface::class, TodoRepository::class)
            ->setPublic(true);

        $c->register(TodoService::class)
            ->setAutowired(true)
            ->setPublic(true);

        $c->register(TodoController::class)
            ->setAutowired(true)
            ->setPublic(true);
    },

    'controllers' => static function (AliasRegistry $r): void {
        $r->registerController('todo', TodoController::class);
    },

    'middlewares' => static function (AliasRegistry $r): void {
        $r->registerMiddleware('csrf', CsrfMiddleware::class);
    },

    'routes' => static function (RouteBuilder $routes): void {
        Route::get(['todo.index', '/todos'])
            ->middleware(['csrf'])
            ->controller('todo@index');

        Route::post(['todo.create', '/todos'])
            ->middleware(['csrf'])
            ->controller('todo@store');

        Route::post(['todo.toggle', '/todos/{id}/toggle'])
            ->middleware(['csrf'])
            ->controller('todo@toggle');
    },
];
