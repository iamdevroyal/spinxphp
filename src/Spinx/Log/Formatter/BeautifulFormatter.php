<?php

declare(strict_types=1);

namespace Spinx\Log\Formatter;

use DateTimeImmutable;
use DateTimeInterface;
use Throwable;

/**
 * High-clarity visual log formatter.
 *
 * Designed to provide immediate readability without the visual clutter of
 * monolithic single-line JSON or 60-line unfiltered vendor stack traces.
 *
 * Features:
 *  - Clear ASCII box dividers for multi-line context and exceptions.
 *  - Origin Highlight: Pinpoints the exact application file and line.
 *  - Noise Filtering: Filters out internal vendor/framework loop frames
 *    while prominently displaying the application's domain/module call stack.
 *  - Relative Paths: Automatically strips the project root prefix for compact paths.
 */
final class BeautifulFormatter implements LogFormatterInterface
{
    private string $projectRoot;

    public function __construct(?string $projectRoot = null)
    {
        $this->projectRoot = $projectRoot !== null
            ? str_replace('\\', '/', rtrim($projectRoot, '/\\'))
            : (defined('SPINX_BASE_PATH') ? str_replace('\\', '/', (string) constant('SPINX_BASE_PATH')) : '');
    }

    public function format(string $level, string|\Stringable $message, array $context = [], string $channel = 'local'): string
    {
        $timestamp = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $upperLevel = strtoupper($level);
        $messageStr = (string) $message;

        $exception = null;
        if (isset($context['exception']) && $context['exception'] instanceof Throwable) {
            $exception = $context['exception'];
            unset($context['exception']);
        }

        // Simple single-line format for simple log messages without complex context or exception
        if ($exception === null && empty($context) && !str_contains($messageStr, "\n")) {
            return sprintf("[%s] %s.%s: %s\n", $timestamp, $channel, $upperLevel, $messageStr);
        }

        // Structured multi-line block format
        $lines = [];
        $lines[] = sprintf("[%s] %s.%s: %s", $timestamp, $channel, $upperLevel, $messageStr);

        $hasContext = !empty($context);
        $hasException = $exception !== null;

        if ($hasContext || $hasException) {
            $lines[] = '┌─ ' . ($hasException ? 'Error Details & Context' : 'Context') . ' ' . str_repeat('─', 40);

            // Print context key-values
            if ($hasContext) {
                foreach ($context as $key => $val) {
                    $formattedVal = $this->formatValue($val);
                    $lines[] = sprintf('│ %-10s: %s', $key, $formattedVal);
                }
            }

            // Print exception origin and filtered trace
            if ($hasException) {
                if ($hasContext) {
                    $lines[] = '├─ Origin ' . str_repeat('─', 54);
                }
                $originFile = $this->normalizePath($exception->getFile());
                $lines[] = sprintf('│ class:  %s', get_class($exception));
                $lines[] = sprintf('│ file:   %s:%d', $originFile, $exception->getLine());
                $lines[] = sprintf('│ error:  %s', $exception->getMessage());

                $lines[] = '├─ Stack Trace (Application Frames) ' . str_repeat('─', 28);
                $traceLines = $this->formatStackTrace($exception);
                foreach ($traceLines as $tLine) {
                    $lines[] = '│ ' . $tLine;
                }
            }

            $lines[] = '└' . str_repeat('─', 64);
        }

        return implode("\n", $lines) . "\n\n";
    }

    private function formatValue(mixed $value): string
    {
        if (is_scalar($value) || $value === null) {
            return var_export($value, true);
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_array($value)) {
            $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            return $json !== false ? $json : '[Array]';
        }

        if (is_object($value)) {
            if (method_exists($value, '__toString')) {
                return (string) $value;
            }
            return sprintf('[Object %s]', get_class($value));
        }

        return get_debug_type($value);
    }

    /**
     * @return string[]
     */
    private function formatStackTrace(Throwable $exception): array
    {
        $trace = $exception->getTrace();
        $output = [];
        $appFramesCount = 0;
        $vendorBatch = 0;

        foreach ($trace as $frame) {
            $file = isset($frame['file']) ? $this->normalizePath((string) $frame['file']) : '[internal function]';
            $line = $frame['line'] ?? 0;
            $call = (isset($frame['class']) ? $frame['class'] . $frame['type'] : '') . ($frame['function'] ?? '') . '()';

            $isVendor = str_contains($file, 'vendor/') || str_contains($file, '[internal');

            if ($isVendor) {
                $vendorBatch++;
                continue;
            }

            // Flush any accumulated vendor frames before showing application frame
            if ($vendorBatch > 0) {
                $output[] = sprintf('↳ [%d framework/vendor frames omitted]', $vendorBatch);
                $vendorBatch = 0;
            }

            $appFramesCount++;
            $output[] = sprintf('#%d %s:%d → %s', $appFramesCount, $file, $line, $call);
        }

        if ($vendorBatch > 0) {
            $output[] = sprintf('↳ [%d framework/vendor frames omitted]', $vendorBatch);
        }

        if (empty($output)) {
            $output[] = '↳ (No application stack frames in trace)';
        }

        return $output;
    }

    private function normalizePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        if ($this->projectRoot !== '' && str_starts_with($path, $this->projectRoot . '/')) {
            return substr($path, strlen($this->projectRoot) + 1);
        }

        return $path;
    }
}
