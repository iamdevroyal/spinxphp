<?php

declare(strict_types=1);

namespace Symfony\Component\HttpFoundation {
    if (!class_exists(HeaderBag::class)) {
        class HeaderBag {
            private array $headers = [];
            public function set(string $k, string $v): void { $this->headers[strtolower($k)] = $v; }
            public function get(string $k, ?string $default = null): ?string { return $this->headers[strtolower($k)] ?? $default; }
            public function has(string $k): bool { return isset($this->headers[strtolower($k)]); }
        }
    }

    if (!class_exists(ParameterBag::class)) {
        class ParameterBag {
            public function __construct(private array $params = []) {}
            public function get(string $k, mixed $default = null): mixed { return $this->params[$k] ?? $default; }
            public function set(string $k, mixed $v): void { $this->params[$k] = $v; }
            public function all(): array { return $this->params; }
        }
    }

    if (!class_exists(Response::class)) {
        class Response {
            public HeaderBag $headers;
            public function __construct(protected ?string $content = '', protected int $status = 200) {
                $this->headers = new HeaderBag();
            }
            public function getContent(): string { return $this->content ?? ''; }
            public function getStatusCode(): int { return $this->status; }
        }
    }

    if (!class_exists(JsonResponse::class)) {
        class JsonResponse extends Response {
            public function __construct(mixed $data = null, int $status = 200) {
                parent::__construct(json_encode($data), $status);
                $this->headers->set('Content-Type', 'application/json');
            }
        }
    }

    if (!class_exists(Request::class)) {
        class Request {
            public HeaderBag $headers;
            public ParameterBag $attributes;
            public ParameterBag $request;
            public ParameterBag $cookies;
            private string $pathInfo;
            private string $method;

            public function __construct(string $uri = '/', string $method = 'GET') {
                $this->pathInfo = $uri;
                $this->method = $method;
                $this->headers = new HeaderBag();
                $this->attributes = new ParameterBag();
                $this->request = new ParameterBag();
                $this->cookies = new ParameterBag();
            }

            public static function create(string $uri, string $method = 'GET', array $parameters = []): static {
                $req = new static($uri, $method);
                $req->request = new ParameterBag($parameters);
                return $req;
            }

            public function getPathInfo(): string { return $this->pathInfo; }
            public function getMethod(): string { return $this->method; }
            public function isXmlHttpRequest(): bool { return false; }
        }
    }
}
