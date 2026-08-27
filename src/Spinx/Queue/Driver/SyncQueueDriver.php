<?php

declare(strict_types=1);

namespace Spinx\Queue\Driver;

use Spinx\Queue\Job;

/**
 * Synchronous queue driver — executes jobs immediately in-process.
 */
final class SyncQueueDriver implements QueueDriverInterface
{
    public function push(Job $job, string $queue = 'default', int $delaySeconds = 0, int $priority = 0): string
    {
        $job->handle();

        return $this->generateUuid();
    }

    public function pop(string $queue = 'default'): ?array
    {
        return null;
    }

    public function ack(mixed $id, string $jobRef): void
    {
    }

    public function fail(mixed $id, string $jobRef, \Throwable $e, Job $job, int $attempts): void
    {
    }

    public function release(mixed $id, string $jobRef, int $delaySeconds = 0): void
    {
    }

    public function size(string $queue = 'default'): int
    {
        return 0;
    }

    public function clear(string $queue = 'default'): void
    {
    }

    private function generateUuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
