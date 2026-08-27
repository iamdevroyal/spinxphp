<?php

declare(strict_types=1);

namespace Spinx\Queue;

use Spinx\Queue\Driver\DatabaseQueueDriver;
use Spinx\Queue\Driver\QueueDriverInterface;
use Spinx\Queue\Driver\RedisQueueDriver;
use Spinx\Queue\Driver\SyncQueueDriver;
use Spinx\Support\Config;

/**
 * Queue manager coordinating queue drivers, multi-named queues, priorities, and workers.
 */
final class QueueManager
{
    /** @var array<string, QueueDriverInterface> */
    private array $drivers = [];
    private string $activeQueue = 'default';
    private int $activePriority = 0;

    public function __construct(
        private readonly ?string $defaultConnection = null,
    ) {
    }

    /**
     * Get a queue driver connection by name.
     */
    public function connection(?string $name = null): QueueDriverInterface
    {
        $name = $name ?? $this->getDefaultConnection();

        return $this->drivers[$name] ??= $this->resolve($name);
    }

    public function getDefaultConnection(): string
    {
        return $this->defaultConnection 
            ?? (string) Config::get('queue.default', env('QUEUE_CONNECTION', 'database'));
    }

    /**
     * Fluent queue selector.
     */
    public function onQueue(string $queue): self
    {
        $clone = clone $this;
        $clone->activeQueue = $queue;

        return $clone;
    }

    /**
     * Fluent priority setter.
     */
    public function withPriority(int $priority): self
    {
        $clone = clone $this;
        $clone->activePriority = $priority;

        return $clone;
    }

    /**
     * Push a job onto the queue.
     *
     * @return string Job UUID reference
     */
    public function push(Job $job, ?string $queue = null, int $delaySeconds = 0, ?int $priority = null): string
    {
        $targetQueue = $queue ?? $this->activeQueue;
        $targetPriority = $priority ?? $this->activePriority;

        return $this->connection()->push($job, $targetQueue, $delaySeconds, $targetPriority);
    }

    /**
     * Push a job with a delayed execution time in seconds.
     */
    public function later(int $delaySeconds, Job $job, ?string $queue = null): string
    {
        return $this->push($job, $queue, $delaySeconds);
    }

    /**
     * Backward-compatible dispatch method.
     */
    public function dispatch(Job $job, int $delaySeconds = 0, string $queue = 'default', int $priority = 0): string
    {
        return $this->push($job, $queue, $delaySeconds, $priority);
    }

    /**
     * Run a job immediately in-process.
     */
    public function dispatchSync(Job $job): void
    {
        $job->handle();
    }

    private function resolve(string $name): QueueDriverInterface
    {
        $driver = Config::get("queue.connections.{$name}.driver", $name);

        return match ($driver) {
            'database' => new DatabaseQueueDriver(),
            'redis'    => new RedisQueueDriver(),
            'sync'     => new SyncQueueDriver(),
            default    => throw new \InvalidArgumentException("Queue driver [{$driver}] is not supported."),
        };
    }

    public function __call(string $method, array $arguments): mixed
    {
        return $this->connection()->$method(...$arguments);
    }
}
