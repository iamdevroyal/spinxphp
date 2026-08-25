<?php

declare(strict_types=1);

namespace Spinx\Database\Connection;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;

/**
 * Swoole coroutines share a single OS process, so a naive
 * single-connection-per-worker strategy (fine for RoadRunner, see
 * RoadRunnerConnectionManager) would let two concurrent coroutines issue
 * queries over the same connection simultaneously and corrupt results.
 * This manager keeps a real checkout/return pool sized up to
 * $poolSize, growing lazily rather than pre-opening every connection
 * at boot.
 *
 * NOTE: this class only loads/instantiates when the Swoole driver is
 * actually selected (build spec §2.3 — opt-in, Docker/Linux only), so it's
 * safe for this file to reference Swoole\Coroutine\Channel even though
 * the extension isn't a hard dependency of the framework as a whole.
 */
final class SwooleConnectionManager implements ConnectionManager
{
    private ?\Swoole\Coroutine\Channel $pool = null;
    private int $openConnections = 0;

    /** @param array<string, mixed> $dbalParams */
    public function __construct(
        private readonly array $dbalParams,
        private readonly int $poolSize = 10,
    ) {
        if (!class_exists(\Swoole\Coroutine\Channel::class)) {
            throw new \RuntimeException(
                'SwooleConnectionManager requires the Swoole/OpenSwoole extension. ' .
                'Set "driver": "roadrunner" in spinx.json if it is not installed.'
            );
        }
    }

    public function get(): Connection
    {
        $this->pool ??= new \Swoole\Coroutine\Channel($this->poolSize);

        // Grow the pool lazily: if nothing is checked in yet but we
        // haven't hit the cap, open a fresh connection rather than
        // blocking the coroutine on an empty channel.
        if ($this->pool->isEmpty() && $this->openConnections < $this->poolSize) {
            $this->openConnections++;

            return DriverManager::getConnection($this->dbalParams);
        }

        $connection = $this->pool->pop();

        return $connection instanceof Connection ? $connection : DriverManager::getConnection($this->dbalParams);
    }

    public function release(Connection $connection): void
    {
        $this->pool?->push($connection);
    }
}
