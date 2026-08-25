<?php

declare(strict_types=1);

namespace Spinx\Queue;

use Spinx\Database\Model;

/** Internal — QueueManager and the queue:work CLI command are the only things that should touch this directly. */
final class QueuedJobRecord extends Model
{
    protected static string $table = 'spinx_jobs';

    protected array $fillable = ['payload', 'attempts', 'available_at'];

    protected array $casts = ['attempts' => 'int'];
}
