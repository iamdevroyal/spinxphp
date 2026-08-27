<?php

declare(strict_types=1);

namespace Spinx\Routing;

use Symfony\Component\Routing\Route as SymfonyRoute;
use Symfony\Component\Routing\RouteCollection;

/**
 * Collects RouteDefinition objects during module registration and compiles
 * them into Symfony Route objects. Holds the AliasRegistry so middleware
 * and controller aliases can be resolved at compile time (not at request
 * dispatch time — fail-fast is better than a mysterious 500 at runtime).
 *
 * Passed to the module's 'routes' closure by ModuleLoader:
 *
 *   'routes' => static function (RouteBuilder $routes): void {
 *       Route::get(['orders.index', '/orders'])->controller('order_list');
 *   }
 */
final class RouteBuilder
{
    /** @var RouteDefinition[] */
    private array $definitions = [];

    /** @var RouteBuilder[] Child builders created by Route::group() */
    private array $children = [];

    private ?RouteBuilder $parent = null;

    public function __construct(
        private readonly string        $prefix = '',
        private readonly AliasRegistry $aliasRegistry = new AliasRegistry(),
    ) {
    }

    public function getAliasRegistry(): AliasRegistry
    {
        return $this->aliasRegistry;
    }

    public function setParent(RouteBuilder $parent): void
    {
        $this->parent = $parent;
    }

    /** Called by Route::get()/post() etc. to register a pending definition. */
    public function add(RouteDefinition $definition): void
    {
        $this->definitions[] = $definition;
    }

    /** Absorbs a child group's definitions back into this builder. */
    public function mergeChild(RouteBuilder $child): void
    {
        $this->children[] = $child;
    }

    /**
     * Compiles all collected definitions (and child groups) into real
     * Symfony Route objects and adds them to $collection.
     *
     * Called by ModuleLoader once per module, with the top-level
     * RouteBuilder for that module.
     *
     * @throws \RuntimeException If a controller alias cannot be resolved
     */
    public function compileInto(RouteCollection $collection, string $parentPrefix = ''): void
    {
        $effectivePrefix = $parentPrefix . $this->prefix;

        foreach ($this->definitions as $definition) {
            $controllerAlias = $definition->getController();

            if ($controllerAlias === null) {
                throw new \RuntimeException(sprintf(
                    'Route "%s" (%s %s) has no controller. Call ->controller(\'alias\') or ->controller(ControllerClass::class).',
                    $definition->name,
                    $definition->method,
                    $effectivePrefix . $definition->path
                ));
            }

            // Resolve the controller — prefer alias lookup, fall through to
            // treating the value as a literal class-string.
            $controllerClass = $this->aliasRegistry->hasController($controllerAlias)
                ? $this->aliasRegistry->resolveController($controllerAlias)
                : $controllerAlias;

            // Resolve middleware aliases → class-strings.
            $middlewareClasses = array_map(
                fn (string $alias): string => $this->aliasRegistry->hasMiddleware($alias)
                    ? $this->aliasRegistry->resolveMiddleware($alias)
                    : $alias,
                $definition->getMiddlewareAliases()
            );

            $defaults = [
                '_controller' => $controllerClass,
                '_middleware' => $middlewareClasses,
            ];

            if ($definition->isCsrfExempt()) {
                $defaults['_csrf_exempt'] = true;
            }

            $route = new SymfonyRoute(
                $effectivePrefix . $definition->path,
                defaults: $defaults,
                methods: [$definition->method],
            );

            $collection->add($definition->name, $route);
        }

        // Recurse into child groups.
        foreach ($this->children as $child) {
            $child->compileInto($collection, $effectivePrefix);
        }
    }
}
