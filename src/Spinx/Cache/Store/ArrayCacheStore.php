<?php

declare(strict_types=1);

namespace Spinx\Cache\Store;

/**
 * In-memory array cache store.
 * Useful for automated unit testing or short-lived per-request caches.
 */
final class ArrayCacheStore implements CacheStoreInterface
{
    /** @var array<string, array{expires_at: int, value: mixed}> */
    private array $storage = [];

    public function get(string $key, mixed $default = null): mixed
    {
        if (!isset($this->storage[$key])) {
            return $default;
        }

        $item = $this->storage[$key];
        if ($item['expires_at'] !== 0 && time() >= $item['expires_at']) {
            unset($this->storage[$key]);
            return $default;
        }

        return $item['value'];
    }

    public function put(string $key, mixed $value, int|\DateInterval|null $ttl = null): bool
    {
        $expiresAt = $this->calculateExpiration($ttl);
        $this->storage[$key] = [
            'expires_at' => $expiresAt,
            'value'      => $value,
        ];

        return true;
    }

    public function has(string $key): bool
    {
        return $this->get($key, $this) !== $this;
    }

    public function forget(string $key): bool
    {
        unset($this->storage[$key]);

        return true;
    }

    public function flush(): bool
    {
        $this->storage = [];

        return true;
    }

    public function increment(string $key, int $value = 1): int|bool
    {
        $current = $this->get($key, 0);
        if (!is_numeric($current)) {
            return false;
        }

        $new = (int) $current + $value;
        $this->put($key, $new);

        return $new;
    }

    public function decrement(string $key, int $value = 1): int|bool
    {
        return $this->increment($key, -$value);
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

    private function calculateExpiration(int|\DateInterval|null $ttl): int
    {
        if ($ttl === null || $ttl === 0) {
            return 0;
        }

        if ($ttl instanceof \DateInterval) {
            return (new \DateTimeImmutable())->add($ttl)->getTimestamp();
        }

        return time() + $ttl;
    }
}
