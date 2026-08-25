<?php

declare(strict_types=1);

namespace Spinx\Log;

use Psr\Log\LoggerInterface;

/**
 * Static Facade for the Spinx Logging Subsystem.
 *
 * Usage:
 *   Log::info('User registered', ['user_id' => 42]);
 *   Log::error('Order failed', ['exception' => $e]);
 *   Log::channel('daily')->warning('Low disk space');
 *   Log::stack(['daily', 'stderr'])->alert('Database unresponsive');
 */
final class Log
{
    private static ?LogManager $manager = null;

    public static function setManager(LogManager $manager): void
    {
        self::$manager = $manager;
    }

    public static function getManager(): LogManager
    {
        if (self::$manager === null) {
            self::$manager = new LogManager();
        }

        return self::$manager;
    }

    public static function channel(?string $name = null): LoggerInterface
    {
        return self::getManager()->channel($name);
    }

    /**
     * @param string[] $channels
     */
    public static function stack(array $channels, ?string $channelName = 'stack'): LoggerInterface
    {
        return self::getManager()->stack($channels, $channelName);
    }

    public static function emergency(string|\Stringable $message, array $context = []): void
    {
        self::getManager()->emergency($message, $context);
    }

    public static function alert(string|\Stringable $message, array $context = []): void
    {
        self::getManager()->alert($message, $context);
    }

    public static function critical(string|\Stringable $message, array $context = []): void
    {
        self::getManager()->critical($message, $context);
    }

    public static function error(string|\Stringable $message, array $context = []): void
    {
        self::getManager()->error($message, $context);
    }

    public static function warning(string|\Stringable $message, array $context = []): void
    {
        self::getManager()->warning($message, $context);
    }

    public static function notice(string|\Stringable $message, array $context = []): void
    {
        self::getManager()->notice($message, $context);
    }

    public static function info(string|\Stringable $message, array $context = []): void
    {
        self::getManager()->info($message, $context);
    }

    public static function debug(string|\Stringable $message, array $context = []): void
    {
        self::getManager()->debug($message, $context);
    }

    public static function log(mixed $level, string|\Stringable $message, array $context = []): void
    {
        self::getManager()->log($level, $message, $context);
    }
}
