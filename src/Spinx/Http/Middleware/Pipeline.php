<?php

declare(strict_types=1);

namespace Spinx\Http\Middleware;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Wraps a final request handler (the controller) with a chain of
 * middleware, resolved through the container so middleware can have
 * autowired dependencies just like controllers and services.
 */
final class Pipeline
{
    public function __construct(
        private readonly ContainerInterface $container,
    ) {
    }

    /**
     * @param string[] $middlewareClasses Applied in array order — the
     *        first class listed runs first and wraps everything after it
     * @param \Closure(Request): Response $finalHandler The controller call
     */
    public function handle(Request $request, array $middlewareClasses, \Closure $finalHandler): Response
    {
        // Build the chain from the inside out: start with the controller
        // as the innermost handler, then wrap each middleware around it
        // in reverse order, so the FIRST middleware in the array ends up
        // as the OUTERMOST wrapper and therefore runs first.
        $handler = $finalHandler;

        foreach (array_reverse($middlewareClasses) as $middlewareClass) {
            $middleware = $this->resolve($middlewareClass);
            $next = $handler;
            $handler = static fn (Request $req): Response => $middleware->process($req, $next);
        }

        return $handler($request);
    }

    private function resolve(string $middlewareClass): MiddlewareInterface
    {
        // Falls back to a bare `new` for middleware that was never
        // registered in the container — a reasonable convenience for
        // simple, dependency-free middleware, but anything needing
        // autowired constructor dependencies must be registered with
        // ->setPublic(true) in module.php (see make:middleware's printed
        // snippet), or this throws a plain missing-argument error, not a
        // silently wrong instance.
        $instance = $this->container->has($middlewareClass)
            ? $this->container->get($middlewareClass)
            : new $middlewareClass();

        if (!$instance instanceof MiddlewareInterface) {
            throw new \RuntimeException(sprintf(
                'Middleware "%s" must implement %s.',
                $middlewareClass,
                MiddlewareInterface::class
            ));
        }

        return $instance;
    }
}
