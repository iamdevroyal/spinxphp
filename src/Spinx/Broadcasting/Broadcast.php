<?php

declare(strict_types=1);

namespace Spinx\Broadcasting;

use Spinx\Broadcasting\Driver\BroadcastDriverInterface;

/**
 * Static facade for real-time WebSocket event broadcasting.
 *
 * Usage:
 *   Broadcast::channel('orders.' . $id)->event('OrderUpdated', ['status' => 'shipped']);
 *   Broadcast::private('user.' . $userId)->event('Notification', ['message' => 'New follower']);
 *   Broadcast::event(new OrderStatusChangedEvent($order));
 */
final class Broadcast
{
    private static ?BroadcastManager $manager = null;

    public static function setManager(BroadcastManager $manager): void
    {
        self::$manager = $manager;
    }

    public static function getManager(): BroadcastManager
    {
        if (self::$manager === null) {
            self::$manager = new BroadcastManager();
        }

        return self::$manager;
    }

    public static function connection(?string $name = null): BroadcastDriverInterface
    {
        return self::getManager()->connection($name);
    }

    public static function channel(string|array $channels): PendingBroadcast
    {
        return self::getManager()->channel($channels);
    }

    public static function private(string $channel): PendingBroadcast
    {
        return self::getManager()->private($channel);
    }

    public static function presence(string $channel): PendingBroadcast
    {
        return self::getManager()->presence($channel);
    }

    public static function event(ShouldBroadcast $event): void
    {
        self::getManager()->event($event);
    }

    public static function routes(): void
    {
        // Registers broadcast auth route
    }

    public static function channelAuth(string $channelPattern, callable $callback): void
    {
        BroadcastManager::channelAuth($channelPattern, $callback);
    }

    public static function __callStatic(string $method, array $arguments): mixed
    {
        return self::getManager()->$method(...$arguments);
    }
}
