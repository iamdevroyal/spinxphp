<?php

declare(strict_types=1);

namespace Spinx\Cache\Store;

/**
 * Redis cache store backend.
 */
final class RedisCacheStore implements CacheStoreInterface
{
    public function __construct(
        private readonly mixed $client,
        private readonly string $prefix = 'spinx_cache:',
    ) {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->client->get($this->prefix . $key);

        if ($value === false || $value === null) {
            return $default;
        }

        $unserialized = @unserialize($value);
        return $unserialized !== false || $value === 'b:0;' ? $unserialized : $value;
    }

    public function put(string $key, mixed $value, int|\DateInterval|null $ttl = null): bool
    {
        $serialized = serialize($value);
        $seconds = $this->calculateTtlSeconds($ttl);

        if ($seconds > 0) {
            return (bool) $this->client->setex($this->prefix . $key, $seconds, $serialized);
        }

        return (bool) $this->client->set($this->prefix . $key, $serialized);
    }

    public function has(string $key): bool
    {
        return (bool) $this->client->exists($this->prefix . $key);
    }

    public function forget(string $key): bool
    {
        return (bool) $this->client->del($this->prefix . $key);
    }

    public function flush(): bool
    {
        if (method_exists($this->client, 'keys')) {
            $keys = $this->client->keys($this->prefix . '*');
            if (!empty($keys)) {
                $this->client->del($keys);
            }
            return true;
        }

        return (bool) $this->client->flushdb();
    }

    public function increment(string $key, int $value = 1): int|bool
    {
        return $this->client->incrby($this->prefix . $key, $value);
    }

    public function decrement(string $key, int $value = 1): int|bool
    {
        return $this->client->decrby($this->prefix . $key, $value);
    }

    public function remember(string $key, int|\DateInterval|null $ttl, \Closure $callback): mixed
    {
        $val = $this->get($key, $this);

        if ($val !== $this) {
            return $val;
        }

        $result = $callback();
        $this->put($key, $result, $ttl);

        return $result;
    }

    private function calculateTtlSeconds(int|\DateInterval|null $ttl): int
    {
        if ($ttl === null) {
            return 0;
        }

        if ($ttl instanceof \DateInterval) {
            return (int) (new \DateTimeImmutable())->add($ttl)->getTimestamp() - time();
        }

        return $ttl;
    }
}
