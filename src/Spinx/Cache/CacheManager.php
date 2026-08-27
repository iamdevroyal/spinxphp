<?php

declare(strict_types=1);

namespace Spinx\Cache;

use Spinx\Cache\Store\ArrayCacheStore;
use Spinx\Cache\Store\CacheStoreInterface;
use Spinx\Cache\Store\FileCacheStore;
use Spinx\Cache\Store\RedisCacheStore;
use Spinx\Support\Config;

/**
 * Cache manager orchestrating cache drivers and stores.
 */
final class CacheManager
{
    /** @var array<string, CacheStoreInterface> */
    private array $stores = [];

    public function __construct(
        private readonly string $projectRoot,
        private readonly ?string $defaultDriver = null,
    ) {
    }

    /**
     * Get a cache store instance by name.
     */
    public function store(?string $name = null): CacheStoreInterface
    {
        $name = $name ?? $this->getDefaultDriver();

        return $this->stores[$name] ??= $this->resolve($name);
    }

    public function getDefaultDriver(): string
    {
        return $this->defaultDriver 
            ?? (string) Config::get('cache.default', env('CACHE_DRIVER', 'file'));
    }

    private function resolve(string $name): CacheStoreInterface
    {
        return match ($name) {
            'file'  => $this->createFileStore(),
            'array' => new ArrayCacheStore(),
            'redis' => $this->createRedisStore(),
            default => throw new \InvalidArgumentException("Cache store [{$name}] is not supported."),
        };
    }

    private function createFileStore(): FileCacheStore
    {
        $path = (string) Config::get('cache.stores.file.path', $this->projectRoot . '/storage/cache/data');

        return new FileCacheStore($path);
    }

    private function createRedisStore(): RedisCacheStore
    {
        if (!extension_loaded('redis')) {
            throw new \RuntimeException('The redis PHP extension is required to use the Redis cache store.');
        }

        $prefix = (string) Config::get('cache.prefix', 'spinx_cache:');
        $client = \Spinx\Redis\Redis::connection('cache');

        return new RedisCacheStore($client, $prefix);
    }

    public function __call(string $method, array $arguments): mixed
    {
        return $this->store()->$method(...$arguments);
    }
}
