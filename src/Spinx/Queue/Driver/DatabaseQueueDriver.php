<?php

declare(strict_types=1);

namespace Spinx\Queue\Driver;

use Spinx\Database\DB;
use Spinx\Queue\FailedJobRecord;
use Spinx\Queue\Job;
use Spinx\Queue\QueuedJobRecord;

/**
 * Robust database-backed queue driver.
 * Supports multi-queues, priorities, retry delays, and failed job tracking.
 */
final class DatabaseQueueDriver implements QueueDriverInterface
{
    public function push(Job $job, string $queue = 'default', int $delaySeconds = 0, int $priority = 0): string
    {
        $jobRef = $this->generateUuid();
        $availableAt = (new \DateTimeImmutable("+{$delaySeconds} seconds"))->format('Y-m-d H:i:s');

        QueuedJobRecord::create([
            'job_ref'      => $jobRef,
            'queue'        => $queue,
            'payload'      => $this->serializePayload($job),
            'priority'     => $priority,
            'attempts'     => 0,
            'reserved_at'  => null,
            'available_at' => $availableAt,
        ]);

        return $jobRef;
    }

    public function pop(string $queue = 'default'): ?array
    {
        $now = date('Y-m-d H:i:s');
        $timeoutThreshold = date('Y-m-d H:i:s', time() - 300); // 5 min reservation timeout

        return DB::transaction(function () use ($queue, $now, $timeoutThreshold): ?array {
            // Find next eligible job: available, and either not reserved or timed out
            $sql = 'SELECT id, job_ref, payload, attempts, queue FROM spinx_jobs 
                    WHERE queue = :queue 
                      AND available_at <= :now 
                      AND (reserved_at IS NULL OR reserved_at <= :threshold)
                    ORDER BY priority DESC, available_at ASC 
                    LIMIT 1';

            $row = DB::selectOne($sql, [
                'queue'     => $queue,
                'now'       => $now,
                'threshold' => $timeoutThreshold,
            ]);

            if ($row === null) {
                return null;
            }

            $id = $row['id'];
            $attempts = (int) $row['attempts'] + 1;

            // Reserve the job
            DB::statement(
                'UPDATE spinx_jobs SET reserved_at = :reserved_at, attempts = :attempts WHERE id = :id',
                ['reserved_at' => $now, 'attempts' => $attempts, 'id' => $id]
            );

            $job = $this->unserializePayload((string) $row['payload']);
            if (!$job instanceof Job) {
                $this->ack($id, (string) $row['job_ref']);
                return null;
            }

            return [
                'id'       => $id,
                'job_ref'  => (string) $row['job_ref'],
                'job'      => $job,
                'attempts' => $attempts,
                'queue'    => (string) $row['queue'],
            ];
        });
    }

    public function ack(mixed $id, string $jobRef): void
    {
        try {
            DB::statement('DELETE FROM spinx_jobs WHERE id = :id', ['id' => $id]);
        } catch (\Throwable) {
        }
    }

    public function fail(mixed $id, string $jobRef, \Throwable $e, Job $job, int $attempts): void
    {
        try {
            $this->ack($id, $jobRef);

            FailedJobRecord::create([
                'job_ref'   => $jobRef,
                'queue'     => 'default',
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
            $availableAt = (new \DateTimeImmutable("+{$delaySeconds} seconds"))->format('Y-m-d H:i:s');
            DB::statement(
                'UPDATE spinx_jobs SET reserved_at = NULL, available_at = :avail WHERE id = :id',
                ['avail' => $availableAt, 'id' => $id]
            );
        } catch (\Throwable) {
        }
    }

    public function size(string $queue = 'default'): int
    {
        try {
            $count = DB::scalar('SELECT COUNT(*) FROM spinx_jobs WHERE queue = :queue', ['queue' => $queue]);
            return (int) $count;
        } catch (\Throwable) {
            return 0;
        }
    }

    public function clear(string $queue = 'default'): void
    {
        try {
            DB::statement('DELETE FROM spinx_jobs WHERE queue = :queue', ['queue' => $queue]);
        } catch (\Throwable) {
        }
    }

    private function generateUuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    private function serializePayload(Job $job): string
    {
        $serialized = serialize($job);
        $key = (string) env('APP_KEY', 'spinx-secret-key-123456');
        $hash = hash_hmac('sha256', $serialized, $key);

        return json_encode([
            'data' => base64_encode($serialized),
            'hmac' => $hash,
        ], JSON_THROW_ON_ERROR);
    }

    private function unserializePayload(string $rawPayload): ?Job
    {
        $decoded = json_decode($rawPayload, true);
        if (!is_array($decoded) || empty($decoded['data']) || empty($decoded['hmac'])) {
            // Backward compatibility fallback for legacy un-hashed payloads
            $unb64 = base64_decode($rawPayload, true);
            if ($unb64 !== false) {
                $j = @unserialize($unb64, ['allowed_classes' => true]);
                return $j instanceof Job ? $j : null;
            }
            return null;
        }

        $serialized = base64_decode((string) $decoded['data'], true);
        if ($serialized === false) {
            return null;
        }

        $key = (string) env('APP_KEY', 'spinx-secret-key-123456');
        $expectedHmac = hash_hmac('sha256', $serialized, $key);

        if (!hash_equals($expectedHmac, (string) $decoded['hmac'])) {
            \Spinx\Log\Log::error('Cryptographic HMAC verification failed on queue payload. Tampering suspected.');
            return null;
        }

        $job = @unserialize($serialized, ['allowed_classes' => true]);
        return $job instanceof Job ? $job : null;
    }
}
