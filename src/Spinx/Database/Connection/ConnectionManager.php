<?php

declare(strict_types=1);

namespace Spinx\Database\Connection;

use Doctrine\DBAL\Connection;

/**
 * Connection pooling strategy differs fundamentally between runtime
 * drivers (build spec §7.3), so this is an interface rather than a single
 * implementation: RoadRunner workers are single-threaded processes with
 * no in-process concurrency, so one persistent connection per worker is
 * both sufficient and safe. Swoole coroutines share a single OS process,
 * so a naive single connection would be handed to multiple concurrent
 * coroutines and corrupt queries mid-flight — Swoole needs a real
 * checkout/return pool instead.
 */
interface ConnectionManager
{
    /** Checks out a connection safe to use for the current request/coroutine. */
    public function get(): Connection;

    /** Returns a connection to the pool once the request/coroutine is done with it. */
    public function release(Connection $connection): void;
}
