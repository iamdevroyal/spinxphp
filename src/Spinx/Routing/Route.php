<?php

declare(strict_types=1);

namespace Spinx\Routing;

use Symfony\Component\Routing\Route as SymfonyRoute;
use Symfony\Component\Routing\RouteCollection;

/**
 * Fluent route builder — the primary API for registering routes in module.php.
 *
 * Replaces the old raw Symfony RouteCollection/Route object API entirely.
 * Every fluent definition is stored in a pending state and compiled into
 * real Symfony Route objects by RouteBuilder::compileInto() when the
 * ModuleLoader calls it. Symfony's router is still the actual engine —
 * this class is purely a builder/DSL wrapper, not a reimplementation.
 *
 * Usage in module.php:
 *
 *   'routes' => static function (RouteBuilder $routes): void {
 *       Route::get(['health.check', '/health'])
 *           ->middleware(['auth', 'rate_limit'])
 *           ->controller('health_controller');
 *
 *       Route::group('/api/v1', function (RouteBuilder $group): void {
 *           Route::get(['orders.index', '/orders'])->controller('order_list_controller');
 *           Route::post(['orders.create', '/orders'])->controller('order_create_controller');
 *           Route::get(['orders.show', '/orders/{id}'])->controller('order_show_controller');
 *       });
 *   },
 *
 * The $routes RouteBuilder argument is provided by ModuleLoader — module
 * authors never construct one directly.
 */
final class Route
{
    /**
     * The RouteBuilder currently being populated — set by RouteBuilder before
     * calling the module's 'routes' closure and cleared after.
     * Thread/coroutine safety: this is written ONCE during the compilation
     * phase at boot (single-threaded in RoadRunner workers, and
     * ModuleLoader is not called concurrently in Swoole either because
     * routing is compiled synchronously at Kernel::boot() before any
     * coroutines are spawned).
     */
    private static ?RouteBuilder $activeBuilder = null;

    /**
     * Set by RouteBuilder before invoking the 'routes' closure.
     * @internal
     */
    public static function setActiveBuilder(RouteBuilder $builder): void
    {
        self::$activeBuilder = $builder;
    }

    /** @internal */
    public static function clearActiveBuilder(): void
    {
        self::$activeBuilder = null;
    }

    // ---------------------------------------------------------------
    // HTTP method shorthands
    // ---------------------------------------------------------------

    /**
     * @param array{0: string, 1: string} $nameAndPath [routeName, path]
     */
    public static function get(array $nameAndPath): RouteDefinition
    {
        return self::add('GET', $nameAndPath);
    }

    /** @param array{0: string, 1: string} $nameAndPath */
    public static function post(array $nameAndPath): RouteDefinition
    {
        return self::add('POST', $nameAndPath);
    }

    /** @param array{0: string, 1: string} $nameAndPath */
    public static function put(array $nameAndPath): RouteDefinition
    {
        return self::add('PUT', $nameAndPath);
    }

    /** @param array{0: string, 1: string} $nameAndPath */
    public static function patch(array $nameAndPath): RouteDefinition
    {
        return self::add('PATCH', $nameAndPath);
    }

    /** @param array{0: string, 1: string} $nameAndPath */
    public static function delete(array $nameAndPath): RouteDefinition
    {
        return self::add('DELETE', $nameAndPath);
    }

    /**
     * Groups routes under a shared prefix. All routes registered inside
     * the $callback will have the prefix prepended to their path.
     *
     * Route::group('/api/v1', function (RouteBuilder $group): void {
     *     Route::get(['users.index', '/users'])->controller('user_list');
     * });
     * // Registers: GET /api/v1/users   named "users.index"
     */
    public static function group(string $prefix, \Closure $callback): void
    {
        $builder = self::requireBuilder();
        $child   = new RouteBuilder($prefix, $builder->getAliasRegistry());
        $child->setParent($builder);

        $previous = self::$activeBuilder;
        self::$activeBuilder = $child;

        $callback($child);

        self::$activeBuilder = $previous;

        // Merge child definitions back into the parent
        $builder->mergeChild($child);
    }

    // ---------------------------------------------------------------
    // Internal
    // ---------------------------------------------------------------

    /** @param array{0: string, 1: string} $nameAndPath */
    private static function add(string $method, array $nameAndPath): RouteDefinition
    {
        [$name, $path] = $nameAndPath;
        $builder = self::requireBuilder();
        $definition = new RouteDefinition($name, $path, $method);
        $builder->add($definition);

        return $definition;
    }

    private static function requireBuilder(): RouteBuilder
    {
        if (self::$activeBuilder === null) {
            throw new \LogicException(
                'Route::get/post/put/patch/delete/group() must be called inside the \'routes\' closure of a module.php. ' .
                'No active RouteBuilder is set — do not call Route:: methods outside of a module registration context.'
            );
        }

        return self::$activeBuilder;
    }
}
