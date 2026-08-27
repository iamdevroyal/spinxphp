<?php

declare(strict_types=1);

namespace Spinx\Queue;

use Spinx\Database\Model;

/**
 * Active Record model for pending/running jobs stored in database queues.
 */
final class QueuedJobRecord extends Model
{
    protected static string $table = 'spinx_jobs';

    protected array $fillable = [
        'job_ref',
        'queue',
        'payload',
        'priority',
        'attempts',
        'reserved_at',
        'available_at',
    ];

    protected array $casts = [
        'priority' => 'int',
        'attempts' => 'int',
    ];
}
