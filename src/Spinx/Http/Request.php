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
    /** @var array<int, SymfonyRequest> */
    private static array $coroutineRequests = [];

    /** @internal Called by Kernel::handle() on every inbound request */
    public static function setCurrentRequest(?SymfonyRequest $request): void
    {
        if (function_exists('swoole_coroutine_get_cid') && swoole_coroutine_get_cid() > 0) {
            $cid = (int) swoole_coroutine_get_cid();
            if ($request === null) {
                unset(self::$coroutineRequests[$cid]);
            } else {
                self::$coroutineRequests[$cid] = $request;
            }
            return;
        }

        self::$current = $request;
    }

    public static function instance(): SymfonyRequest
    {
        if (function_exists('swoole_coroutine_get_cid') && swoole_coroutine_get_cid() > 0) {
            $cid = (int) swoole_coroutine_get_cid();
            if (isset(self::$coroutineRequests[$cid])) {
                return self::$coroutineRequests[$cid];
            }
        }

        if (self::$current === null) {
            self::$current = SymfonyRequest::createFromGlobals();
        }

        return self::$current;
    }

    /**
     * Get the raw, unmodified request body bytes (crucial for webhook signature verification).
     */
    public static function rawBody(): string
    {
        return (string) self::instance()->getContent();
    }

    /**
     * Retrieve the JSON decoded payload or a specific key from it.
     */
    public static function json(?string $key = null, mixed $default = null): mixed
    {
        $raw = self::rawBody();
        if ($raw === '') {
            return $key === null ? [] : $default;
        }

        $decoded = @json_decode($raw, true);
        if (!is_array($decoded)) {
            return $key === null ? [] : $default;
        }

        if ($key === null) {
            return $decoded;
        }

        return $decoded[$key] ?? $default;
    }

    /**
     * Retrieve the Bearer token from the Authorization header if present.
     */
    public static function bearerToken(): ?string
    {
        $header = self::header('Authorization', '');
        if ($header !== null && str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }

        return null;
    }

    public static function all(): array
    {
        $req = self::instance();
        $data = array_merge($req->query->all(), $req->request->all());

        $contentType = $req->headers->get('Content-Type', '');
        if (str_contains($contentType, 'application/json')) {
            $raw = (string) $req->getContent();
            if ($raw !== '') {
                $json = @json_decode($raw, true);
                if (is_array($json)) {
                    $data = array_merge($data, $json);
                }
            }
        }

        return $data;
    }

    public static function input(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return self::all();
        }

        $all = self::all();
        return $all[$key] ?? $default;
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

    public static function session(): ?\Spinx\Session\SessionInterface
    {
        return self::instance()->attributes->get(\Spinx\Session\SessionInterface::class)
            ?? \Spinx\Auth\Auth::getSession();
    }

    public static function old(string $key, mixed $default = null): mixed
    {
        $session = self::session();
        return $session !== null ? $session->get('_old_input.' . $key, $default) : $default;
    }

    public static function user(): ?object
    {
        return Auth::user();
    }


    /**
     * Validate the current request's input against the given rules.
     * Throws ValidationException on failure.
     *
     * Usage:
     *   $validated = Request::validate([
     *       'email'    => 'required|email|max:255',
     *       'password' => 'required|string|min:8',
     *   ]);
     *
     * @param array<string, string> $rules
     * @param array<string, string> $messages
     * @return array<string, mixed>
     * @throws \Spinx\Validation\ValidationException
     */
    public static function validate(array $rules, array $messages = []): array
    {
        return \Spinx\Validation\Validator::make(self::all(), $rules, $messages)->validate();
    }

    /**
     * Check if the request expects a JSON response.
     */
    public static function wantsJson(): bool
    {
        $accept = self::header('Accept', '');
        return str_contains((string) $accept, 'application/json');
    }

    /**
     * Check if the request is an AJAX/XHR request.
     */
    public static function ajax(): bool
    {
        return self::header('X-Requested-With') === 'XMLHttpRequest';
    }

    /**
     * Retrieve only the specified keys from the input.
     *
     * @param string[] $keys
     * @return array<string, mixed>
     */
    public static function only(array $keys): array
    {
        $all = self::all();
        return array_intersect_key($all, array_flip($keys));
    }

    /**
     * Retrieve all input except the specified keys.
     *
     * @param string[] $keys
     * @return array<string, mixed>
     */
    public static function except(array $keys): array
    {
        $all = self::all();
        return array_diff_key($all, array_flip($keys));
    }

    /**
     * Retrieve an uploaded file from the request.
     */
    public static function file(string $key): mixed
    {
        return self::instance()->files->get($key);
    }

    /**
     * Check if a file was uploaded for the given key.
     */
    public static function hasFile(string $key): bool
    {
        return self::instance()->files->has($key);
    }
}

