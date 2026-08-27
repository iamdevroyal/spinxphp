<?php

declare(strict_types=1);

namespace Spinx\Queue\Driver;

use Spinx\Queue\Job;

/**
 * Universal contract for Spinx queue drivers (Database, Redis, Sync, SQS, etc.).
 */
interface QueueDriverInterface
{
    /**
     * Push a new job onto the specified queue.
     *
     * @return string Unique job reference (UUID)
     */
    public function push(Job $job, string $queue = 'default', int $delaySeconds = 0, int $priority = 0): string;

    /**
     * Pop the next available job from the specified queue (respecting priority and availability timestamp).
     *
     * @return array{id: mixed, job_ref: string, job: Job, attempts: int, queue: string}|null
     */
    public function pop(string $queue = 'default'): ?array;

    /**
     * Acknowledge successful completion of a job.
     */
    public function ack(mixed $id, string $jobRef): void;

    /**
     * Mark a job as failed, recording error details.
     */
    public function fail(mixed $id, string $jobRef, \Throwable $e, Job $job, int $attempts): void;

    /**
     * Release a failed or retried job back into the queue with an optional delay.
     */
    public function release(mixed $id, string $jobRef, int $delaySeconds = 0): void;

    /**
     * Get the count of pending jobs in a queue.
     */
    public function size(string $queue = 'default'): int;

    /**
     * Clear all pending jobs in a queue.
     */
    public function clear(string $queue = 'default'): void;
}
