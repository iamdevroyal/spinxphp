<?php

declare(strict_types=1);

namespace Spinx\Http\Middleware;

use Spinx\Http\RateLimit\{InMemoryRateLimitStore, RateLimitStore};
use Spinx\Support\Config;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Config-driven rate limiting (see config/rate_limit.php) — attach per
 * route/module, not globally by default, same reasoning as
 * CorsMiddleware: a framework shouldn't assume every route needs the
 * same protection.
 *
 * The store is held in a STATIC property — deliberately, unlike the
 * request-scoped-by-default state-safety rule that applies to
 * app/Modules services (build spec §4). A rate limiter reset on every
 * request would rate-limit nothing at all; the whole point is a counter
 * that survives across requests within the same persistent worker. This
 * is the same category of legitimate framework-level static state as
 * Spinx\Database\Model's connection manager — see that class's own
 * docblock for the fuller reasoning, and InMemoryRateLimitStore's
 * docblock for the real, honestly-documented multi-worker limitation
 * this default store has.
 */
final class RateLimitMiddleware implements MiddlewareInterface
{
    private static ?RateLimitStore $store = null;

    public function process(Request $request, \Closure $next): Response
    {
        $store = self::$store ??= new InMemoryRateLimitStore();

        $maxAttempts = (int) Config::instance()->get('rate_limit.max_attempts', 60);
        $decaySeconds = (int) Config::instance()->get('rate_limit.decay_seconds', 60);
        $key = $request->getClientIp() ?? 'unknown';

        if ($store->attempts($key) >= $maxAttempts) {
            $response = new Response('Too Many Requests', 429);
            $response->headers->set('Retry-After', (string) $store->availableIn($key));
            $response->headers->set('X-RateLimit-Limit', (string) $maxAttempts);
            $response->headers->set('X-RateLimit-Remaining', '0');

            return $response;
        }

        $count = $store->increment($key, $decaySeconds);
        $response = $next($request);

        $response->headers->set('X-RateLimit-Limit', (string) $maxAttempts);
        $response->headers->set('X-RateLimit-Remaining', (string) max(0, $maxAttempts - $count));

        return $response;
    }
}
