<?php

declare(strict_types=1);

namespace Spinx\Queue;

use Spinx\Database\Model;

/** Internal — jobs land here after exceeding max attempts. Inspect via Model methods or a DB browser. */
final class FailedJobRecord extends Model
{
    protected static string $table = 'spinx_failed_jobs';

    protected array $fillable = ['payload', 'exception', 'failed_at'];
}
