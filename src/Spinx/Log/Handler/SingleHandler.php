<?php

declare(strict_types=1);

namespace Spinx\Log\Handler;

use Spinx\Log\Formatter\LogFormatterInterface;

/**
 * Handler that appends log entries to a single continuous file.
 */
final class SingleHandler extends AbstractHandler
{
    public function __construct(
        private readonly string $filePath,
        string $minLevel = 'debug',
        ?LogFormatterInterface $formatter = null
    ) {
        parent::__construct($minLevel, $formatter);
    }

    protected function write(string $formatted): void
    {
        $dir = dirname($this->filePath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        @file_put_contents($this->filePath, $formatted, FILE_APPEND | LOCK_EX);
    }
}
