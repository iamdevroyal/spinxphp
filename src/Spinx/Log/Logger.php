<?php

declare(strict_types=1);

namespace Spinx\Log;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Spinx\Log\Handler\LogHandlerInterface;

/**
 * PSR-3 Compliant Logger implementation for Spinx.
 * Dispatches log messages to one or more configured handlers.
 */
final class Logger implements LoggerInterface
{
    use LoggerTrait;

    /** @var LogHandlerInterface[] */
    private array $handlers;

    /**
     * @param string $name Name of the logger channel
     * @param LogHandlerInterface[] $handlers
     */
    public function __construct(
        private readonly string $name = 'local',
        array $handlers = []
    ) {
        $this->handlers = $handlers;
    }

    public function addHandler(LogHandlerInterface $handler): self
    {
        $this->handlers[] = $handler;
        return $this;
    }

    /**
     * @return LogHandlerInterface[]
     */
    public function getHandlers(): array
    {
        return $this->handlers;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed $level
     * @param string|\Stringable $message
     * @param array<string, mixed> $context
     */
    public function log(mixed $level, string|\Stringable $message, array $context = []): void
    {
        $levelStr = (string) $level;

        foreach ($this->handlers as $handler) {
            $handler->handle($levelStr, $message, $context, $this->name);
        }
    }
}
