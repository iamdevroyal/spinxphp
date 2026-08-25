<?php

declare(strict_types=1);

namespace Symfony\Component\Routing {
    if (!class_exists(Route::class)) {
        class Route {
            public function __construct(
                private string $path,
                private array $defaults = [],
                private array $requirements = [],
                private array $options = [],
                private ?string $host = '',
                private array|string $schemes = [],
                private array|string $methods = [],
                private ?string $condition = ''
            ) {}

            public function getPath(): string { return $this->path; }
            public function getDefaults(): array { return $this->defaults; }
            public function getDefault(string $k): mixed { return $this->defaults[$k] ?? null; }
            public function getMethods(): array { return is_array($this->methods) ? $this->methods : [$this->methods]; }
        }
    }

    if (!class_exists(RouteCollection::class)) {
        class RouteCollection {
            private array $routes = [];
            public function add(string $name, Route $route): void { $this->routes[$name] = $route; }
            public function get(string $name): ?Route { return $this->routes[$name] ?? null; }
            public function all(): array { return $this->routes; }
        }
    }

    if (!class_exists(RequestContext::class)) {
        class RequestContext {
            private string $path = '/';
            public function fromRequest($request): void {
                if (method_exists($request, 'getPathInfo')) {
                    $this->path = $request->getPathInfo();
                }
            }
            public function getPath(): string { return $this->path; }
        }
    }
}

namespace Symfony\Component\Routing\Matcher {
    use Symfony\Component\Routing\RouteCollection;
    use Symfony\Component\Routing\RequestContext;

    if (!class_exists(UrlMatcher::class)) {
        class UrlMatcher {
            public function __construct(private RouteCollection $routes, private RequestContext $context) {}

            public function match(string $path): array {
                foreach ($this->routes->all() as $name => $route) {
                    $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $route->getPath());
                    $pattern = '#^' . $pattern . '$#';
                    if (preg_match($pattern, $path, $matches)) {
                        $params = $route->getDefaults();
                        $params['_route'] = $name;
                        foreach ($matches as $k => $v) {
                            if (is_string($k)) {
                                $params[$k] = $v;
                            }
                        }
                        return $params;
                    }
                }
                throw new \RuntimeException("Route not found for path: {$path}");
            }
        }
    }
}
