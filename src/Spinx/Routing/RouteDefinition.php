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
     * @param string[] $aliases Middleware aliases registered in the 'middlewares' closure
     */
    public function middleware(array $aliases): static
    {
        $this->middlewareAliases = $aliases;

        return $this;
    }

    /**
     * @param string $aliasOrClass Controller alias (from 'controllers' closure) or a FQCN
     */
    public function controller(string $aliasOrClass): static
    {
        $this->controller = $aliasOrClass;

        return $this;
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
