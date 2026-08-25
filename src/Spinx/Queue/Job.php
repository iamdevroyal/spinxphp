<?php

declare(strict_types=1);

namespace Spinx\Queue;

/**
 * Jobs are serialized with PHP's native serialize() when dispatched to
 * the queue (see QueueManager::dispatch()) — keep constructor properties
 * to simple, serializable values (IDs, strings, arrays), not live
 * objects like an open database connection or a Model instance loaded
 * from a since-closed request. Re-fetch what you need inside handle().
 */
interface Job
{
    public function handle(): void;
}
