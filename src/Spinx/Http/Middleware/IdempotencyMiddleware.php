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
final class IdempotencyMiddleware
{
    private const DEFAULT_TTL_SECONDS = 86400; // 24 hours

    /**
     * @param mixed $request
     * @param \Closure(mixed): mixed $next
     */
    public function handle(mixed $request, \Closure $next, int $ttl = self::DEFAULT_TTL_SECONDS): mixed
    {
        $idempotencyKey = $this->extractIdempotencyKey();

        // If not a mutation request or no idempotency header is provided, proceed normally
        if ($idempotencyKey === null || !$this->isMutationMethod()) {
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

            header('Idempotent-Replay: true', replace: true);

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

    private function extractIdempotencyKey(): ?string
    {
        $key = $_SERVER['HTTP_IDEMPOTENCY_KEY']
            ?? $_SERVER['HTTP_X_IDEMPOTENCY_KEY']
            ?? null;

        return $key !== null && trim($key) !== '' ? trim($key) : null;
    }

    private function isMutationMethod(): bool
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        return in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }
}
