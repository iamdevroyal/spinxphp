<?php

declare(strict_types=1);

namespace Spinx\Log\Handler;

use Spinx\Log\Formatter\BeautifulFormatter;
use Spinx\Log\Formatter\LogFormatterInterface;

/**
 * No-op handler that ignores all log records.
 */
final class NullHandler implements LogHandlerInterface
{
    private LogFormatterInterface $formatter;

    public function __construct()
    {
        $this->formatter = new BeautifulFormatter();
    }

    public function handle(string $level, string|\Stringable $message, array $context = [], string $channel = 'local'): void
    {
        // No-op
    }

    public function isHandling(string $level): bool
    {
        return false;
    }

    public function setFormatter(LogFormatterInterface $formatter): self
    {
        $this->formatter = $formatter;
        return $this;
    }

    public function getFormatter(): LogFormatterInterface
    {
        return $this->formatter;
    }
}
