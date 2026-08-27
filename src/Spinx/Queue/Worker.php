<?php

declare(strict_types=1);

namespace Spinx\Queue;

use Spinx\Log\Log;
use Spinx\Queue\Driver\QueueDriverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Robust queue worker polling and processing jobs across multiple named queues.
 */
final class Worker
{
    private bool $shouldQuit = false;

    public function __construct(
        private readonly QueueManager $manager,
        private readonly ?ContainerInterface $container = null,
    ) {
        if ($this->container !== null) {
            JobContext::setContainer($this->container);
        }
    }

    /**
     * Run the worker loop continuously.
     *
     * @param string[] $queues List of queue names to poll in priority order
     * @param int $sleep Seconds to sleep when queues are empty
     * @param int $maxTries Maximum retry attempts before marking job as failed
     * @param int $memoryLimitMb Max memory limit in megabytes before graceful restart
     */
    public function daemon(
        array $queues = ['default'],
        int $sleep = 2,
        int $maxTries = 3,
        int $memoryLimitMb = 128,
        ?callable $onJobProcessed = null
    ): void {
        $this->listenForSignals();

        while (!$this->shouldQuit) {
            $processed = $this->runNextJob($queues, $maxTries, $onJobProcessed);

            if (!$processed) {
                sleep($sleep);
            }

            if ($this->memoryExceeded($memoryLimitMb)) {
                break;
            }
        }
    }

    /**
     * Process the next single job from the queue list.
     *
     * @param string[] $queues
     * @return bool True if a job was found and processed, false if queues are empty
     */
    public function runNextJob(
        array $queues = ['default'],
        int $maxTries = 3,
        ?callable $onJobProcessed = null
    ): bool {
        $driver = $this->manager->connection();

        foreach ($queues as $queue) {
            $jobData = $driver->pop($queue);

            if ($jobData !== null) {
                $this->processJob($driver, $jobData, $maxTries, $onJobProcessed);
                return true;
            }
        }

        return false;
    }

    /**
     * @param array{id: mixed, job_ref: string, job: Job, attempts: int, queue: string} $jobData
     */
    private function processJob(
        QueueDriverInterface $driver,
        array $jobData,
        int $maxTries,
        ?callable $onJobProcessed = null
    ): void {
        $id       = $jobData['id'];
        $jobRef   = $jobData['job_ref'];
        $job      = $jobData['job'];
        $attempts = $jobData['attempts'];
        $queue    = $jobData['queue'];

        try {
            $job->handle();
            $driver->ack($id, $jobRef);

            if ($onJobProcessed !== null) {
                $onJobProcessed('success', $jobRef, $queue);
            }
        } catch (\Throwable $e) {
            Log::error("Queue job [{$jobRef}] on queue [{$queue}] failed: " . $e->getMessage(), [
                'exception' => $e,
                'job_ref'   => $jobRef,
                'attempts'  => $attempts,
            ]);

            if ($attempts >= $maxTries) {
                $driver->fail($id, $jobRef, $e, $job, $attempts);

                if ($onJobProcessed !== null) {
                    $onJobProcessed('failed', $jobRef, $queue, $e);
                }
            } else {
                // Exponential backoff: 2s, 4s, 8s...
                $delay = (int) pow(2, $attempts);
                $driver->release($id, $jobRef, $delay);

                if ($onJobProcessed !== null) {
                    $onJobProcessed('retried', $jobRef, $queue, $e);
                }
            }
        }
    }

    private function memoryExceeded(int $memoryLimitMb): bool
    {
        return (memory_get_usage(true) / 1024 / 1024) >= $memoryLimitMb;
    }

    private function listenForSignals(): void
    {
        if (extension_loaded('pcntl')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGTERM, fn() => $this->shouldQuit = true);
            pcntl_signal(SIGINT, fn() => $this->shouldQuit = true);
        }
    }
}
