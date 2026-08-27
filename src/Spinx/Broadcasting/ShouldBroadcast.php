<?php

declare(strict_types=1);

namespace Spinx\Broadcasting;

/**
 * Interface for application events that should automatically broadcast over WebSockets.
 */
interface ShouldBroadcast
{
    /**
     * Get the channels the event should broadcast on.
     *
     * @return Channel|Channel[]|string|string[]
     */
    public function broadcastOn(): Channel|array|string;

    /**
     * The event name to broadcast as.
     */
    public function broadcastAs(): ?string;

    /**
     * The data payload to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array;
}
