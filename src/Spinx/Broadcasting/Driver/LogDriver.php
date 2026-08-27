<?php

declare(strict_types=1);

namespace Spinx\Broadcasting\Driver;

use Spinx\Log\Log;

/**
 * Log broadcast driver — dumps broadcast events to the Spinx logger.
 * Perfect for local development without running a WebSocket server.
 */
final class LogDriver implements BroadcastDriverInterface
{
    public function broadcast(array $channels, string $event, array $payload): void
    {
        $channelList = implode(', ', $channels);
        $payloadJson = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        Log::info("Broadcast Event [{$event}] to channels [{$channelList}]:\n{$payloadJson}");
    }

    public function authenticateChannel(string $channel, string $socketId, ?array $userData = null): array|false
    {
        return [
            'auth' => "log-driver-key:dummy-signature-{$socketId}",
        ];
    }
}
