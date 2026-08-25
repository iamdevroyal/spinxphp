<?php

declare(strict_types=1);

namespace Spinx\Routing;

use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Unified registry for controller and middleware alias → class-string maps.
 *
 * Populated by the 'controllers' and 'middlewares' closures in module.php.
 * Also auto-registers each controller and middleware class with the DI
 * container as a public, autowired service — so module authors don't need
 * a separate 'services' block for anything they register through aliases.
 *
 * The 'services' closure in module.php remains for non-controller,
 * non-middleware DI bindings (repositories, application services, etc.).
 */
final class AliasRegistry
{
    /** @var array<string, string> alias => FQCN */
    private array $controllers = [];

    /** @var array<string, string> alias => FQCN */
    private array $middlewares = [];

    // ---------------------------------------------------------------
    // Controller aliases
    // ---------------------------------------------------------------

    public function registerController(string $alias, string $class): void
    {
        $this->controllers[$alias] = $class;
    }

    public function hasController(string $alias): bool
    {
        return isset($this->controllers[$alias]);
    }

    /** @throws \RuntimeException If the alias is not registered */
    public function resolveController(string $alias): string
    {
        return $this->controllers[$alias] ?? throw new \RuntimeException(
            "Controller alias \"{$alias}\" is not registered. " .
            "Add it to the 'controllers' closure in the relevant module.php."
        );
    }

    /** @return array<string, string> */
    public function allControllers(): array
    {
        return $this->controllers;
    }

    // ---------------------------------------------------------------
    // Middleware aliases
    // ---------------------------------------------------------------

    public function registerMiddleware(string $alias, string $class): void
    {
        $this->middlewares[$alias] = $class;
    }

    public function hasMiddleware(string $alias): bool
    {
        return isset($this->middlewares[$alias]);
    }

    /** @throws \RuntimeException If the alias is not registered */
    public function resolveMiddleware(string $alias): string
    {
        return $this->middlewares[$alias] ?? throw new \RuntimeException(
            "Middleware alias \"{$alias}\" is not registered. " .
            "Add it to the 'middlewares' closure in the relevant module.php."
        );
    }

    /** @return array<string, string> */
    public function allMiddlewares(): array
    {
        return $this->middlewares;
    }

    // ---------------------------------------------------------------
    // DI registration helpers
    // ---------------------------------------------------------------

    /**
     * Auto-registers all known controllers and middlewares into the
     * Symfony DI container as public, autowired services.
     *
     * Called by ModuleLoader after all modules have had their
     * 'controllers'/'middlewares' closures run but before the container
     * is compiled — so everything is available for autowiring.
     */
    public function registerServicesInContainer(ContainerBuilder $container): void
    {
        foreach ([...$this->controllers, ...$this->middlewares] as $class) {
            if (!$container->has($class)) {
                $container->register($class)
                    ->setAutowired(true)
                    ->setPublic(true);
            }
        }
    }
}
