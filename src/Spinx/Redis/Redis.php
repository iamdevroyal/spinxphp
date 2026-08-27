<?php

declare(strict_types=1);

namespace Spinx\Redis;

/**
 * Static facade for accessing Redis connections.
 *
 * Usage:
 *   Redis::set('key', 'value');
 *   $val = Redis::get('key');
 *   Redis::connection('session')->setex('sess_123', 3600, $data);
 */
final class Redis
{
    private static ?RedisManager $manager = null;

    public static function setManager(RedisManager $manager): void
    {
        self::$manager = $manager;
    }

    public static function getManager(): RedisManager
    {
        if (self::$manager === null) {
            self::$manager = new RedisManager();
        }

        return self::$manager;
    }

    public static function connection(?string $name = null): \Redis
    {
        return self::getManager()->connection($name);
    }

    public static function __callStatic(string $method, array $arguments): mixed
    {
        return self::getManager()->connection()->$method(...$arguments);
    }
}
