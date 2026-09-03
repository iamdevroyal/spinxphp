<?php

declare(strict_types=1);

namespace Spinx\Routing;

/**
 * A single pending route definition — returned by Route::get() etc. and
 * populated by the fluent ->middleware()->controller() chain before
 * RouteBuilder compiles it into a Symfony Route.
 */
final class RouteDefinition
{
    /** @var string[] Middleware aliases, resolved to class-strings at compile time */
    private array $middlewareAliases = [];

    /** Controller alias or class-string */
    private ?string $controller = null;

    public function __construct(
        public readonly string $name,
        public readonly string $path,
        public readonly string $method,
    ) {
    }

    /**
     * @param string|string[] ...$aliases Middleware aliases registered in the 'middlewares' closure
     */
    public function middleware(string|array ...$aliases): static
    {
        $flattened = [];
        foreach ($aliases as $alias) {
            if (is_array($alias)) {
                $flattened = array_merge($flattened, $alias);
            } else {
                $flattened[] = (string) $alias;
            }
        }
        $this->middlewareAliases = array_merge($this->middlewareAliases, $flattened);

        return $this;
    }


    /**
     * @param string|array{0: string|object, 1: string} $aliasOrClass Controller alias, FQCN, or [Class, method]
     */
    public function controller(string|array $aliasOrClass, ?string $method = null): static
    {
        if (is_array($aliasOrClass)) {
            $class = is_object($aliasOrClass[0]) ? get_class($aliasOrClass[0]) : (string) $aliasOrClass[0];
            $this->controller = "{$class}@{$aliasOrClass[1]}";
        } elseif ($method !== null) {
            $this->controller = "{$aliasOrClass}@{$method}";
        } else {
            $this->controller = $aliasOrClass;
        }

        return $this;
    }

    /**
     * Alias for controller()
     */
    public function action(string|array $action): static
    {
        return $this->controller($action);
    }

    private bool $csrfExempt = false;

    /**
     * Exempt this route from CSRF verification (useful for external webhooks).
     */
    public function withoutCsrf(bool $exempt = true): static
    {
        $this->csrfExempt = $exempt;

        return $this;
    }

    public function isCsrfExempt(): bool
    {
        return $this->csrfExempt;
    }

    /** @return string[] */
    public function getMiddlewareAliases(): array
    {
        return $this->middlewareAliases;
    }

    public function getController(): ?string
    {
        return $this->controller;
    }
}
