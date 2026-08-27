<?php

declare(strict_types=1);

namespace Spinx\Broadcasting;

/**
 * Represents an authenticated private broadcast channel.
 */
final class PrivateChannel extends Channel
{
    public function __construct(string $name)
    {
        $channelName = str_starts_with($name, 'private-') ? $name : 'private-' . $name;
        parent::__construct($channelName);
    }
}
