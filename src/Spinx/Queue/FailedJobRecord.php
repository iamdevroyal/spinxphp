<?php

declare(strict_types=1);

namespace Spinx\Queue;

use Spinx\Database\Model;

/**
 * Active Record model for failed jobs.
 */
final class FailedJobRecord extends Model
{
    protected static string $table = 'spinx_failed_jobs';

    protected array $fillable = [
        'job_ref',
        'queue',
        'payload',
        'exception',
        'failed_at',
    ];
}
