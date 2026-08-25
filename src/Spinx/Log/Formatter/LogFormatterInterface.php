<?php

declare(strict_types=1);

namespace Spinx\Log\Formatter;

/**
 * Contract for converting log entries into written string representations.
 */
interface LogFormatterInterface
{
    /**
     * @param string $level Log level (emergency, alert, critical, error, warning, notice, info, debug)
     * @param string|\Stringable $message
     * @param array<string, mixed> $context
     * @param string $channel Name of the log channel (e.g. local, daily, production)
     */
    public function format(string $level, string|\Stringable $message, array $context = [], string $channel = 'local'): string;
}
