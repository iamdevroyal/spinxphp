<?php

declare(strict_types=1);

namespace Spinx\Cache;

use Spinx\Cache\Store\CacheStoreInterface;

/**
 * Static facade for Cache operations.
 *
 * Usage:
 *   Cache::put('stats', $stats, 3600);
 *   $stats = Cache::get('stats', []);
 *   $user = Cache::remember('user:1', 300, fn() => User::find(1));
 *   Cache::forget('stats');
 *   Cache::flush();
 */
final class Cache
{
    private static ?CacheManager $manager = null;

    public static function setManager(CacheManager $manager): void
    {
        self::$manager = $manager;
    }

    public static function getManager(): CacheManager
    {
        if (self::$manager === null) {
            $projectRoot = defined('SPINX_PROJECT_ROOT') 
                ? (string) constant('SPINX_PROJECT_ROOT') 
                : dirname(__DIR__, 3);

            self::$manager = new CacheManager($projectRoot);
        }

        return self::$manager;
    }

    public static function store(?string $name = null): CacheStoreInterface
    {
        return self::getManager()->store($name);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::getManager()->get($key, $default);
    }

    public static function put(string $key, mixed $value, int|\DateInterval|null $ttl = null): bool
    {
        return self::getManager()->put($key, $value, $ttl);
    }

    public static function has(string $key): bool
    {
        return self::getManager()->has($key);
    }

    public static function forget(string $key): bool
    {
        return self::getManager()->forget($key);
    }

    public static function flush(): bool
    {
        return self::getManager()->flush();
    }

    public static function increment(string $key, int $value = 1): int|bool
    {
        return self::getManager()->increment($key, $value);
    }

    public static function decrement(string $key, int $value = 1): int|bool
    {
        return self::getManager()->decrement($key, $value);
    }

    public static function remember(string $key, int|\DateInterval|null $ttl, \Closure $callback): mixed
    {
        return self::getManager()->remember($key, $ttl, $callback);
    }
}
