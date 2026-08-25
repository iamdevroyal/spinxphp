<?php

declare(strict_types=1);

namespace Spinx\Log\Handler;

use DateTimeImmutable;
use Spinx\Log\Formatter\LogFormatterInterface;

/**
 * Handler that creates a daily rotated log file (spinx-YYYY-MM-DD.log)
 * and automatically prunes log files older than the retention period.
 */
final class DailyHandler extends AbstractHandler
{
    private string $directory;
    private string $filenamePrefix;
    private string $filenameExtension;
    private int $maxDays;
    private static ?string $lastPrunedDate = null;

    public function __construct(
        string $basePath,
        int $maxDays = 14,
        string $minLevel = 'debug',
        ?LogFormatterInterface $formatter = null
    ) {
        parent::__construct($minLevel, $formatter);

        $pathInfo = pathinfo($basePath);
        $this->directory = $pathInfo['dirname'] ?? '.';
        $this->filenamePrefix = $pathInfo['filename'] ?? 'spinx';
        $this->filenameExtension = isset($pathInfo['extension']) && $pathInfo['extension'] !== '' ? '.' . $pathInfo['extension'] : '.log';
        $this->maxDays = max(1, $maxDays);
    }

    protected function write(string $formatted): void
    {
        if (!is_dir($this->directory)) {
            @mkdir($this->directory, 0755, true);
        }

        $today = (new DateTimeImmutable())->format('Y-m-d');
        $currentLogPath = $this->directory . '/' . $this->filenamePrefix . '-' . $today . $this->filenameExtension;

        @file_put_contents($currentLogPath, $formatted, FILE_APPEND | LOCK_EX);

        // Prune old logs once per day per process
        if (self::$lastPrunedDate !== $today) {
            self::$lastPrunedDate = $today;
            $this->pruneOldLogs();
        }
    }

    private function pruneOldLogs(): void
    {
        $pattern = $this->directory . '/' . $this->filenamePrefix . '-*' . $this->filenameExtension;
        $files = glob($pattern);

        if ($files === false || count($files) <= $this->maxDays) {
            return;
        }

        // Sort files chronologically ascending
        sort($files);

        $filesToDelete = array_slice($files, 0, count($files) - $this->maxDays);
        foreach ($filesToDelete as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }
}
