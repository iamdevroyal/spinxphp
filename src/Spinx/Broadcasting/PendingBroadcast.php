<?php

declare(strict_types=1);

namespace Spinx\Broadcasting;

/**
 * Fluent pending broadcast dispatcher.
 */
final class PendingBroadcast
{
    /** @var string[] */
    private array $channels = [];

    public function __construct(
        private readonly BroadcastManager $manager,
        array $channels,
    ) {
        foreach ($channels as $channel) {
            $this->channels[] = (string) $channel;
        }
    }

    /**
     * Dispatch an event name and payload to the targeted channels.
     *
     * @param string $event
     * @param array<string, mixed> $payload
     */
    public function event(string $event, array $payload = []): void
    {
        $this->manager->connection()->broadcast($this->channels, $event, $payload);
    }
}
