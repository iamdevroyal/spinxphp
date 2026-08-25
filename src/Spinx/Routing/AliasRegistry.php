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
        if (str_contains($alias, '@')) {
            [$baseAlias] = explode('@', $alias, 2);
            return isset($this->controllers[$baseAlias]) || class_exists($baseAlias);
        }

        return isset($this->controllers[$alias]) || class_exists($alias);
    }

    /** @throws \RuntimeException If the alias is not registered */
    public function resolveController(string $alias): string
    {
        if (str_contains($alias, '@')) {
            [$baseAlias, $method] = explode('@', $alias, 2);
            $baseClass = $this->controllers[$baseAlias] ?? (class_exists($baseAlias) ? $baseAlias : null);

            if ($baseClass === null) {
                throw new \RuntimeException(
                    "Controller alias \"{$baseAlias}\" is not registered. " .
                    "Add it to the 'controllers' closure in the relevant module.php."
                );
            }

            return "{$baseClass}@{$method}";
        }

        if (isset($this->controllers[$alias])) {
            return $this->controllers[$alias];
        }

        if (class_exists($alias)) {
            return $alias;
        }

        throw new \RuntimeException(
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
        return isset($this->middlewares[$alias]) || class_exists($alias);
    }

    /** @throws \RuntimeException If the alias is not registered */
    public function resolveMiddleware(string $alias): string
    {
        if (isset($this->middlewares[$alias])) {
            return $this->middlewares[$alias];
        }

        if (class_exists($alias)) {
            return $alias;
        }

        throw new \RuntimeException(
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
            $baseClass = str_contains($class, '@') ? explode('@', $class, 2)[0] : $class;
            if (class_exists($baseClass) && !$container->has($baseClass)) {
                $container->register($baseClass)
                    ->setAutowired(true)
                    ->setPublic(true);
            }
        }
    }
}
