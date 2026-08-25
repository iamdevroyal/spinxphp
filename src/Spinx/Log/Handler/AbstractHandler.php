<?php

declare(strict_types=1);

namespace Spinx\Log\Handler;

use Spinx\Log\Formatter\BeautifulFormatter;
use Spinx\Log\Formatter\LogFormatterInterface;

abstract class AbstractHandler implements LogHandlerInterface
{
    protected const LEVELS = [
        'debug'     => 100,
        'info'      => 200,
        'notice'    => 250,
        'warning'   => 300,
        'error'     => 400,
        'critical'  => 500,
        'alert'     => 550,
        'emergency' => 600,
    ];

    protected int $minLevelValue;
    protected LogFormatterInterface $formatter;

    public function __construct(
        string $minLevel = 'debug',
        ?LogFormatterInterface $formatter = null
    ) {
        $this->minLevelValue = self::LEVELS[strtolower($minLevel)] ?? 100;
        $this->formatter = $formatter ?? new BeautifulFormatter();
    }

    public function isHandling(string $level): bool
    {
        $val = self::LEVELS[strtolower($level)] ?? 100;
        return $val >= $this->minLevelValue;
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

    abstract protected function write(string $formatted): void;

    public function handle(string $level, string|\Stringable $message, array $context = [], string $channel = 'local'): void
    {
        if (!$this->isHandling($level)) {
            return;
        }

        $formatted = $this->formatter->format($level, $message, $context, $channel);
        $this->write($formatted);
    }
}
