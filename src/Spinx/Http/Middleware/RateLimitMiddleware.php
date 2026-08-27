<?php

declare(strict_types=1);

namespace Spinx\Http\Middleware;

use Spinx\Http\RateLimit\{InMemoryRateLimitStore, RateLimitStore, RedisRateLimitStore};
use Spinx\Support\Config;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Config-driven, distributed rate limiting with persistent worker support.
 */
final class RateLimitMiddleware implements MiddlewareInterface
{
    private static ?RateLimitStore $store = null;

    public static function setStore(?RateLimitStore $store): void
    {
        self::$store = $store;
    }

    public static function getStore(): RateLimitStore
    {
        if (self::$store !== null) {
            return self::$store;
        }

        $driver = (string) Config::get('rate_limit.driver', env('RATE_LIMIT_DRIVER', 'auto'));

        if ($driver === 'redis' || ($driver === 'auto' && extension_loaded('redis'))) {
            try {
                return self::$store = new RedisRateLimitStore();
            } catch (\Throwable) {
                // Fall back to in-memory store if Redis server is unreachable
            }
        }

        return self::$store = new InMemoryRateLimitStore();
    }

    public function process(Request $request, \Closure $next): Response
    {
        $store = self::getStore();

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
