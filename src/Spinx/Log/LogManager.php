<?php

declare(strict_types=1);

namespace Spinx\Log;

use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Spinx\Log\Formatter\BeautifulFormatter;
use Spinx\Log\Handler\DailyHandler;
use Spinx\Log\Handler\NullHandler;
use Spinx\Log\Handler\SingleHandler;
use Spinx\Log\Handler\StreamHandler;
use Spinx\Support\Config;

/**
 * LogManager coordinates log channels, handlers, and formats.
 *
 * Implements Psr\Log\LoggerInterface so the manager itself can be injected
 * as a default logger while allowing explicit channel switching via ->channel('name').
 */
final class LogManager implements LoggerInterface
{
    use LoggerTrait;

    /** @var array<string, LoggerInterface> */
    private array $channels = [];
    private string $projectRoot;

    public function __construct(?string $projectRoot = null)
    {
        $this->projectRoot = $projectRoot !== null
            ? rtrim($projectRoot, '/\\')
            : (defined('SPINX_BASE_PATH') ? (string) constant('SPINX_BASE_PATH') : '');
    }

    /**
     * Get a log channel instance.
     */
    public function channel(?string $name = null): LoggerInterface
    {
        $channelName = $name ?? $this->getDefaultDriver();

        if (isset($this->channels[$channelName])) {
            return $this->channels[$channelName];
        }

        return $this->channels[$channelName] = $this->resolve($channelName);
    }

    /**
     * Create a new on-demand stack channel.
     *
     * @param string[] $channels
     */
    public function stack(array $channels, ?string $channelName = 'stack'): LoggerInterface
    {
        $handlers = [];
        foreach ($channels as $cName) {
            $logger = $this->channel($cName);
            if ($logger instanceof Logger) {
                foreach ($logger->getHandlers() as $handler) {
                    $handlers[] = $handler;
                }
            }
        }

        return new Logger($channelName ?? 'stack', $handlers);
    }

    public function getDefaultDriver(): string
    {
        return (string) Config::get('logging.default', 'daily');
    }

    public function log(mixed $level, string|\Stringable $message, array $context = []): void
    {
        $this->channel()->log($level, $message, $context);
    }

    private function resolve(string $name): LoggerInterface
    {
        $config = Config::get("logging.channels.{$name}");

        if (!is_array($config)) {
            // Fallback default config for common channel names if not in config file
            $config = match ($name) {
                'daily'  => ['driver' => 'daily', 'path' => $this->defaultLogPath(), 'days' => 14, 'level' => 'debug'],
                'single' => ['driver' => 'single', 'path' => $this->defaultLogPath(), 'level' => 'debug'],
                'stderr' => ['driver' => 'stderr', 'level' => 'debug'],
                'stdout' => ['driver' => 'stdout', 'level' => 'debug'],
                'null'   => ['driver' => 'null'],
                default  => throw new InvalidArgumentException("Log channel [{$name}] is not configured."),
            };
        }

        $driver = $config['driver'] ?? 'single';
        $level = (string) ($config['level'] ?? 'debug');
        $formatter = new BeautifulFormatter($this->projectRoot);

        return match ($driver) {
            'daily' => new Logger($name, [
                new DailyHandler(
                    $config['path'] ?? $this->defaultLogPath(),
                    (int) ($config['days'] ?? 14),
                    $level,
                    $formatter
                )
            ]),

            'single' => new Logger($name, [
                new SingleHandler(
                    $config['path'] ?? $this->defaultLogPath(),
                    $level,
                    $formatter
                )
            ]),

            'stderr' => new Logger($name, [
                new StreamHandler('php://stderr', $level, $formatter)
            ]),

            'stdout' => new Logger($name, [
                new StreamHandler('php://stdout', $level, $formatter)
            ]),

            'stack' => $this->createStackChannel($name, $config),

            'null' => new Logger($name, [
                new NullHandler()
            ]),

            default => throw new InvalidArgumentException("Unsupported log driver [{$driver}] for channel [{$name}]."),
        };
    }

    /**
     * @param array<string, mixed> $config
     */
    private function createStackChannel(string $name, array $config): LoggerInterface
    {
        $channelNames = (array) ($config['channels'] ?? ['daily']);
        return $this->stack($channelNames, $name);
    }

    private function defaultLogPath(): string
    {
        $base = $this->projectRoot !== '' ? $this->projectRoot : (defined('SPINX_BASE_PATH') ? (string) constant('SPINX_BASE_PATH') : '.');
        return $base . '/storage/logs/spinx.log';
    }
}
