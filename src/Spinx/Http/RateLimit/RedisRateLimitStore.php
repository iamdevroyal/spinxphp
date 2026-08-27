<?php

declare(strict_types=1);

namespace Spinx\Http\RateLimit;

use Spinx\Redis\RedisManager;

/**
 * Production-ready Redis-backed rate limit store.
 * Counters are shared atomically across all RoadRunner/Swoole persistent worker processes.
 */
final class RedisRateLimitStore implements RateLimitStore
{
    private string $prefix;

    public function __construct(
        private readonly ?RedisManager $redis = null,
        string $prefix = 'spinx_ratelimit:',
    ) {
        $this->prefix = $prefix;
    }

    public function attempts(string $key): int
    {
        try {
            $val = $this->getClient()->get($this->prefix . $key);
            return $val !== false && $val !== null ? (int) $val : 0;
        } catch (\Throwable) {
            return 0;
        }
    }

    public function increment(string $key, int $decaySeconds): int
    {
        try {
            $client = $this->getClient();
            $redisKey = $this->prefix . $key;

            $current = (int) $client->incr($redisKey);

            if ($current === 1) {
                $client->expire($redisKey, $decaySeconds);
            }

            return $current;
        } catch (\Throwable) {
            return 1;
        }
    }

    public function availableIn(string $key): int
    {
        try {
            $ttl = $this->getClient()->ttl($this->prefix . $key);
            return $ttl > 0 ? $ttl : 0;
        } catch (\Throwable) {
            return 0;
        }
    }

    private function getClient(): \Redis
    {
        if ($this->redis !== null) {
            return $this->redis->connection('cache');
        }

        return \Spinx\Redis\Redis::connection('cache');
    }
}
