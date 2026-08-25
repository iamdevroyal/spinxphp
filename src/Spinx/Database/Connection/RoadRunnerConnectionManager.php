<?php

declare(strict_types=1);

namespace Spinx\Database\Connection;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;

/**
 * RoadRunner workers are single-threaded PHP processes — there is no
 * in-process concurrency to guard against, unlike Swoole's coroutines
 * sharing one process. A single connection reused across every request
 * handled by this worker is therefore both correct and the fastest
 * option (no pool checkout overhead per request).
 */
final class RoadRunnerConnectionManager implements ConnectionManager
{
    private ?Connection $connection = null;

    /** @param array<string, mixed> $dbalParams */
    public function __construct(
        private readonly array $dbalParams,
    ) {
    }

    public function get(): Connection
    {
        if ($this->connection === null || !$this->connection->isConnected()) {
            $this->connection = DriverManager::getConnection($this->dbalParams);
        }

        return $this->connection;
    }

    public function release(Connection $connection): void
    {
        // No-op: this manager reuses one connection for the life of the
        // worker process — there's no pool to return the connection to.
    }
}
