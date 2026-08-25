<?php

declare(strict_types=1);

namespace Spinx\Http\RateLimit;

/**
 * Default store — plain PHP array, no external dependency required to
 * get rate limiting working at all.
 *
 * KNOWN LIMITATION, stated plainly: this only tracks attempts within a
 * single worker process. RoadRunner and Swoole both run a POOL of
 * workers (build spec §2), and this store's counts are not shared
 * across them — a client's requests could land on different workers and
 * each would count separately, meaning the effective limit is closer to
 * (configured limit × worker count) than the configured limit itself.
 * Correct behavior for a single-worker dev setup or a genuinely
 * low-traffic app; for production traffic across multiple workers, swap
 * in a Redis-backed RateLimitStore implementation (implement the same
 * interface, register it in place of this one in container.php) so
 * every worker shares one counter.
 */
final class InMemoryRateLimitStore implements RateLimitStore
{
    /** @var array<string, array{count: int, resetAt: int}> */
    private array $buckets = [];

    public function attempts(string $key): int
    {
        $this->pruneIfExpired($key);

        return $this->buckets[$key]['count'] ?? 0;
    }

    public function increment(string $key, int $decaySeconds): int
    {
        $this->pruneIfExpired($key);

        $this->buckets[$key] ??= ['count' => 0, 'resetAt' => time() + $decaySeconds];

        return ++$this->buckets[$key]['count'];
    }

    public function availableIn(string $key): int
    {
        return max(0, ($this->buckets[$key]['resetAt'] ?? time()) - time());
    }

    private function pruneIfExpired(string $key): void
    {
        if (isset($this->buckets[$key]) && $this->buckets[$key]['resetAt'] <= time()) {
            unset($this->buckets[$key]);
        }
    }
}
