<?php

declare(strict_types=1);

namespace Spinx\Queue;

/**
 * Deliberately minimal: no priority queues, no multiple named queues, no
 * Redis/SQS driver — a single DB-backed FIFO queue, worked by `spinx
 * queue:work`. This is a real, working starting point matching what a
 * `make:mail`-generated job needs, not a placeholder — swap in a
 * Redis-backed implementation behind the same public interface if/when
 * throughput needs outgrow polling a database table.
 */
final class QueueManager
{
    public function dispatch(Job $job, int $delaySeconds = 0): void
    {
        QueuedJobRecord::create([
            'payload' => base64_encode(serialize($job)),
            'attempts' => 0,
            'available_at' => (new \DateTimeImmutable("+{$delaySeconds} seconds"))->format('Y-m-d H:i:s'),
        ]);
    }

    /** Runs immediately, in-process — no queue table involved. Useful in tests or when a job is cheap enough not to bother deferring. */
    public function dispatchSync(Job $job): void
    {
        $job->handle();
    }
}
