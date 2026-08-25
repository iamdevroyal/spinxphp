<?php

declare(strict_types=1);

namespace Spinx\Http;

use Spinx\Auth\Auth;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

/**
 * Static facade for accessing the active HTTP request in the current worker/request cycle.
 *
 * Usage:
 *   $email = Request::input('email');
 *   $all   = Request::all();
 *   $ip    = Request::ip();
 *   $user  = Request::user();
 */
final class Request
{
    private static ?SymfonyRequest $current = null;

    /** @internal Called by Kernel::handle() on every inbound request */
    public static function setCurrentRequest(?SymfonyRequest $request): void
    {
        self::$current = $request;
    }

    public static function instance(): SymfonyRequest
    {
        if (self::$current === null) {
            self::$current = SymfonyRequest::createFromGlobals();
        }

        return self::$current;
    }

    public static function all(): array
    {
        $req = self::instance();
        return array_merge($req->query->all(), $req->request->all());
    }

    public static function input(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return self::all();
        }

        $req = self::instance();
        return $req->request->get($key, $req->query->get($key, $default));
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::input($key, $default);
    }

    public static function post(?string $key = null, mixed $default = null): mixed
    {
        $req = self::instance();
        if ($key === null) {
            return $req->request->all();
        }

        return $req->request->get($key, $default);
    }

    public static function query(?string $key = null, mixed $default = null): mixed
    {
        $req = self::instance();
        if ($key === null) {
            return $req->query->all();
        }

        return $req->query->get($key, $default);
    }

    public static function has(string $key): bool
    {
        $req = self::instance();
        return $req->request->has($key) || $req->query->has($key);
    }

    public static function filled(string $key): bool
    {
        $value = self::input($key);
        return $value !== null && $value !== '' && $value !== [];
    }

    public static function header(string $key, ?string $default = null): ?string
    {
        return self::instance()->headers->get($key, $default);
    }

    public static function cookie(string $key, ?string $default = null): ?string
    {
        return self::instance()->cookies->get($key, $default);
    }

    public static function ip(): ?string
    {
        return self::instance()->getClientIp();
    }

    public static function method(): string
    {
        return self::instance()->getMethod();
    }

    public static function isMethod(string $method): bool
    {
        return self::instance()->isMethod($method);
    }

    public static function path(): string
    {
        return self::instance()->getPathInfo();
    }

    public static function url(): string
    {
        return self::instance()->getUri();
    }

    public static function user(): ?object
    {
        return Auth::user();
    }
}
