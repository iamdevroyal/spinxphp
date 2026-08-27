<?php

declare(strict_types=1);

namespace Spinx\Queue\Driver;

use Spinx\Queue\FailedJobRecord;
use Spinx\Queue\Job;
use Spinx\Redis\RedisManager;

/**
 * High-throughput Redis queue driver supporting priorities and delayed jobs.
 */
final class RedisQueueDriver implements QueueDriverInterface
{
    private string $prefix = 'spinx:queue:';

    public function __construct(
        private readonly ?RedisManager $redis = null,
    ) {
    }

    public function push(Job $job, string $queue = 'default', int $delaySeconds = 0, int $priority = 0): string
    {
        $jobRef = $this->generateUuid();
        $serialized = serialize($job);
        $key = (string) env('APP_KEY', 'spinx-secret-key-123456');
        $hmac = hash_hmac('sha256', $serialized, $key);

        $payload = json_encode([
            'id'          => $jobRef,
            'job_ref'     => $jobRef,
            'job'         => base64_encode($serialized),
            'hmac'        => $hmac,
            'attempts'    => 0,
            'queue'       => $queue,
            'priority'    => $priority,
            'created_at'  => time(),
        ], JSON_THROW_ON_ERROR);

        $client = $this->getClient();

        if ($delaySeconds > 0) {
            $availableAt = time() + $delaySeconds;
            $client->zAdd($this->prefix . $queue . ':delayed', $availableAt, $payload);
        } else {
            // Sorted set scored by priority (higher priority popped first via ZPOPMAX)
            $client->zAdd($this->prefix . $queue . ':ready', $priority, $payload);
        }

        return $jobRef;
    }

    public function pop(string $queue = 'default'): ?array
    {
        $client = $this->getClient();

        // 1. Move any matured delayed jobs to ready set
        $this->migrateDelayedJobs($queue);

        // 2. Pop highest priority ready job
        $items = $client->zPopMax($this->prefix . $queue . ':ready', 1);
        if (empty($items)) {
            return null;
        }

        $payloadJson = array_key_first($items);
        if ($payloadJson === null || $payloadJson === '') {
            return null;
        }

        try {
            $data = json_decode($payloadJson, true, 512, JSON_THROW_ON_ERROR);
            $serialized = base64_decode((string) ($data['job'] ?? ''), true);

            if ($serialized === false) {
                return null;
            }

            // Verify HMAC signature if present
            if (!empty($data['hmac'])) {
                $key = (string) env('APP_KEY', 'spinx-secret-key-123456');
                $expectedHmac = hash_hmac('sha256', $serialized, $key);
                if (!hash_equals($expectedHmac, (string) $data['hmac'])) {
                    \Spinx\Log\Log::error('Cryptographic HMAC verification failed on Redis queue payload. Tampering suspected.');
                    return null;
                }
            }

            $job = @unserialize($serialized, ['allowed_classes' => true]);
            if (!$job instanceof Job) {
                return null;
            }

            $attempts = ((int) ($data['attempts'] ?? 0)) + 1;
            $data['attempts'] = $attempts;

            return [
                'id'       => $payloadJson,
                'job_ref'  => (string) ($data['job_ref'] ?? $data['id'] ?? ''),
                'job'      => $job,
                'attempts' => $attempts,
                'queue'    => $queue,
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    public function ack(mixed $id, string $jobRef): void
    {
        // For Redis ZPOPMAX, item is already removed from ready set on pop.
    }

    public function fail(mixed $id, string $jobRef, \Throwable $e, Job $job, int $attempts): void
    {
        try {
            FailedJobRecord::create([
                'job_ref'   => $jobRef,
                'queue'     => 'redis',
                'payload'   => base64_encode(serialize($job)),
                'exception' => (string) $e,
                'failed_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
        }
    }

    public function release(mixed $id, string $jobRef, int $delaySeconds = 0): void
    {
        try {
            if (is_string($id)) {
                $data = json_decode($id, true);
                if (is_array($data)) {
                    $queue = (string) ($data['queue'] ?? 'default');
                    $priority = (int) ($data['priority'] ?? 0);
                    $client = $this->getClient();

                    if ($delaySeconds > 0) {
                        $client->zAdd($this->prefix . $queue . ':delayed', time() + $delaySeconds, $id);
                    } else {
                        $client->zAdd($this->prefix . $queue . ':ready', $priority, $id);
                    }
                }
            }
        } catch (\Throwable) {
        }
    }

    public function size(string $queue = 'default'): int
    {
        try {
            $client = $this->getClient();
            $ready = (int) $client->zCard($this->prefix . $queue . ':ready');
            $delayed = (int) $client->zCard($this->prefix . $queue . ':delayed');

            return $ready + $delayed;
        } catch (\Throwable) {
            return 0;
        }
    }

    public function clear(string $queue = 'default'): void
    {
        try {
            $client = $this->getClient();
            $client->del($this->prefix . $queue . ':ready');
            $client->del($this->prefix . $queue . ':delayed');
        } catch (\Throwable) {
        }
    }

    private function migrateDelayedJobs(string $queue): void
    {
        try {
            $client = $this->getClient();
            $now = time();

            // Find all delayed jobs whose timestamp <= now
            $matured = $client->zRangeByScore($this->prefix . $queue . ':delayed', '-inf', (string) $now);

            foreach ($matured as $payloadJson) {
                if ($client->zRem($this->prefix . $queue . ':delayed', $payloadJson) > 0) {
                    $data = json_decode($payloadJson, true);
                    $priority = (int) ($data['priority'] ?? 0);
                    $client->zAdd($this->prefix . $queue . ':ready', $priority, $payloadJson);
                }
            }
        } catch (\Throwable) {
        }
    }

    private function getClient(): \Redis
    {
        if ($this->redis !== null) {
            return $this->redis->connection('queue');
        }

        return \Spinx\Redis\Redis::connection('queue');
    }

    private function generateUuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
