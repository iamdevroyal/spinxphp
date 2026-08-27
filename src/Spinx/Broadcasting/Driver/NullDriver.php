<?php

declare(strict_types=1);

namespace Spinx\Broadcasting\Driver;

/**
 * Null broadcast driver — discards all broadcast events.
 * Intended for test environments.
 */
final class NullDriver implements BroadcastDriverInterface
{
    public function broadcast(array $channels, string $event, array $payload): void
    {
    }

    public function authenticateChannel(string $channel, string $socketId, ?array $userData = null): array|false
    {
        return [
            'auth' => "null:null",
        ];
    }
}
