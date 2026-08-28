<?php

declare(strict_types=1);

namespace Spinx\Http\Middleware;

use Spinx\Cache\Cache;
use Spinx\Http\Response;

/**
 * IdempotencyMiddleware — Prevents duplicate mutations (payments, AI generation, order creation)
 * by caching and replaying responses for requests carrying an `Idempotency-Key` header.
 *
 * Registered globally as the 'idempotent' middleware alias.
 *
 * Usage in module.php:
 *   $routes->post('/api/v1/payments/charge', [PaymentController::class, 'charge'])
 *       ->middleware('idempotent');
 */
final class IdempotencyMiddleware implements MiddlewareInterface
{
    private const DEFAULT_TTL_SECONDS = 86400; // 24 hours

    public function process(\Symfony\Component\HttpFoundation\Request $request, \Closure $next): \Symfony\Component\HttpFoundation\Response
    {
        return $this->handle($request, $next);
    }

    /**
     * @param mixed $request
     * @param \Closure(mixed): mixed $next
     */
    public function handle(mixed $request, \Closure $next, int $ttl = self::DEFAULT_TTL_SECONDS): mixed
    {
        $idempotencyKey = $this->extractIdempotencyKey($request);

        // If not a mutation request or no idempotency header is provided, proceed normally
        if ($idempotencyKey === null || !$this->isMutationMethod($request)) {
            return $next($request);
        }

        $cacheKey = 'idempotency:' . hash('sha256', $idempotencyKey);

        // 1. Check for cached response
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            $status  = (int) ($cached['status'] ?? 200);
            $headers = (array) ($cached['headers'] ?? []);
            $content = (string) ($cached['content'] ?? '');

            $headers['Idempotent-Replay'] = 'true';
            $headers['Idempotency-Key']   = $idempotencyKey;

            return Response::make($content, $status, $headers);
        }


        // 2. Execute the underlying request
        $response = $next($request);

        // 3. Cache successful responses (2xx and 4xx, but not 5xx internal crashes)
        if ($response instanceof \Symfony\Component\HttpFoundation\Response) {
            $status = $response->getStatusCode();
            if ($status < 500) {
                Cache::put($cacheKey, [
                    'status'  => $status,
                    'headers' => $response->headers->allPreserveCase(),
                    'content' => $response->getContent(),
                ], $ttl);
            }
        }

        return $response;
    }

    private function extractIdempotencyKey(mixed $request = null): ?string
    {
        $key = null;

        if ($request instanceof \Symfony\Component\HttpFoundation\Request) {
            $key = $request->headers->get('Idempotency-Key') ?? $request->headers->get('X-Idempotency-Key');
        } elseif (class_exists(\Spinx\Http\Request::class) && method_exists(\Spinx\Http\Request::class, 'header')) {
            $key = \Spinx\Http\Request::header('Idempotency-Key') ?? \Spinx\Http\Request::header('X-Idempotency-Key');
        }

        if ($key === null) {
            $key = $_SERVER['HTTP_IDEMPOTENCY_KEY']
                ?? $_SERVER['HTTP_X_IDEMPOTENCY_KEY']
                ?? null;
        }

        return $key !== null && trim((string) $key) !== '' ? trim((string) $key) : null;
    }

    private function isMutationMethod(mixed $request = null): bool
    {
        $method = '';

        if ($request instanceof \Symfony\Component\HttpFoundation\Request) {
            $method = $request->getMethod();
        } elseif (class_exists(\Spinx\Http\Request::class) && method_exists(\Spinx\Http\Request::class, 'method')) {
            $method = \Spinx\Http\Request::method();
        }

        if ($method === '') {
            $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        }

        return in_array(strtoupper((string) $method), ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }
}

