<?php

declare(strict_types=1);

namespace Spinx\Broadcasting;

/**
 * Represents a public broadcast channel.
 */
class Channel
{
    public function __construct(
        protected readonly string $name,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
