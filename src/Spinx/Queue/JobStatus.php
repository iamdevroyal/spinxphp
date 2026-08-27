<?php

declare(strict_types=1);

namespace Spinx\Queue;

use Spinx\Database\DB;

/**
 * Inspection helper for querying asynchronous job status by UUID job_ref.
 */
final class JobStatus
{
    /**
     * Inspect status of a job.
     *
     * @return array{status: 'pending'|'reserved'|'failed'|'completed', attempts?: int, queue?: string, failed_at?: string, exception?: string}|null
     */
    public static function find(string $jobRef): ?array
    {
        try {
            // Check active/pending table
            $pending = DB::selectOne(
                'SELECT queue, attempts, reserved_at, available_at FROM spinx_jobs WHERE job_ref = :ref',
                ['ref' => $jobRef]
            );

            if ($pending !== null) {
                return [
                    'status'       => $pending['reserved_at'] !== null ? 'reserved' : 'pending',
                    'queue'        => (string) $pending['queue'],
                    'attempts'     => (int) $pending['attempts'],
                    'available_at' => (string) $pending['available_at'],
                ];
            }

            // Check failed table
            $failed = DB::selectOne(
                'SELECT queue, exception, failed_at FROM spinx_failed_jobs WHERE job_ref = :ref',
                ['ref' => $jobRef]
            );

            if ($failed !== null) {
                return [
                    'status'    => 'failed',
                    'queue'     => (string) $failed['queue'],
                    'failed_at' => (string) $failed['failed_at'],
                    'exception' => (string) $failed['exception'],
                ];
            }

            return [
                'status' => 'completed',
            ];
        } catch (\Throwable) {
            return null;
        }
    }
}
