<?php

declare(strict_types=1);

namespace Spinx\Broadcasting;

/**
 * Represents an authenticated presence broadcast channel with peer awareness.
 */
final class PresenceChannel extends Channel
{
    public function __construct(string $name)
    {
        $channelName = str_starts_with($name, 'presence-') ? $name : 'presence-' . $name;
        parent::__construct($channelName);
    }
}
