<?php

declare(strict_types=1);

namespace Spinx\Cache\Store;

/**
 * Common contract for all Spinx cache store backends.
 */
interface CacheStoreInterface
{
    /**
     * Retrieve an item from the cache by key.
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Store an item in the cache for a given number of seconds.
     * If $ttl is null or 0, stores indefinitely.
     */
    public function put(string $key, mixed $value, int|\DateInterval|null $ttl = null): bool;

    /**
     * Check if an item exists in the cache and has not expired.
     */
    public function has(string $key): bool;

    /**
     * Remove an item from the cache.
     */
    public function forget(string $key): bool;

    /**
     * Remove all items from the cache.
     */
    public function flush(): bool;

    /**
     * Increment the numeric value of an item in the cache.
     */
    public function increment(string $key, int $value = 1): int|bool;

    /**
     * Decrement the numeric value of an item in the cache.
     */
    public function decrement(string $key, int $value = 1): int|bool;

    /**
     * Get an item from the cache, or execute the given Closure and store the result.
     */
    public function remember(string $key, int|\DateInterval|null $ttl, \Closure $callback): mixed;
}
