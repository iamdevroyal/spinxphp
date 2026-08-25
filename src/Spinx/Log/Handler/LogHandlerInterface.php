<?php

declare(strict_types=1);

namespace Spinx\Log\Handler;

use Spinx\Log\Formatter\LogFormatterInterface;

/**
 * Contract for log destination handlers.
 */
interface LogHandlerInterface
{
    /**
     * @param string $level Log level name (emergency, alert, critical, error, warning, notice, info, debug)
     * @param string|\Stringable $message
     * @param array<string, mixed> $context
     * @param string $channel
     */
    public function handle(string $level, string|\Stringable $message, array $context = [], string $channel = 'local'): void;

    /**
     * Checks whether this handler is configured to handle the given log level.
     */
    public function isHandling(string $level): bool;

    public function setFormatter(LogFormatterInterface $formatter): self;

    public function getFormatter(): LogFormatterInterface;
}
