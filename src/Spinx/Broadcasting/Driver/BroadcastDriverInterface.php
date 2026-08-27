<?php

declare(strict_types=1);

namespace Spinx\Broadcasting\Driver;

/**
 * Contract for Spinx real-time event broadcasting drivers.
 */
interface BroadcastDriverInterface
{
    /**
     * Broadcast the given event and payload to the given channels.
     *
     * @param string[] $channels
     * @param string $event
     * @param array<string, mixed> $payload
     */
    public function broadcast(array $channels, string $event, array $payload): void;

    /**
     * Authenticate a user's subscription to a private or presence channel.
     *
     * @param string $channel
     * @param string $socketId
     * @param array<string, mixed>|null $userData
     * @return array<string, mixed>|false Auth signature payload or false on failure
     */
    public function authenticateChannel(string $channel, string $socketId, ?array $userData = null): array|false;
}
