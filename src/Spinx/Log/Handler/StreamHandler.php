<?php

declare(strict_types=1);

namespace Spinx\Log\Handler;

use Spinx\Log\Formatter\LogFormatterInterface;

/**
 * Handler that writes log entries directly to a PHP output stream (e.g. php://stderr, php://stdout).
 */
final class StreamHandler extends AbstractHandler
{
    /** @var resource|null */
    private $stream = null;
    private string $streamUri;

    public function __construct(
        string $streamUri = 'php://stderr',
        string $minLevel = 'debug',
        ?LogFormatterInterface $formatter = null
    ) {
        parent::__construct($minLevel, $formatter);
        $this->streamUri = $streamUri;
    }

    protected function write(string $formatted): void
    {
        if ($this->stream === null || !is_resource($this->stream)) {
            $this->stream = @fopen($this->streamUri, 'ab');
        }

        if (is_resource($this->stream)) {
            @fwrite($this->stream, $formatted);
        }
    }

    public function __destruct()
    {
        if ($this->stream !== null && is_resource($this->stream) && !in_array($this->streamUri, ['php://stdout', 'php://stderr'], true)) {
            @fclose($this->stream);
        }
    }
}
