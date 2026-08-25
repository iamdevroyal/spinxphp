<?php

declare(strict_types=1);

namespace Spinx\Database\Connection;

use Spinx\Support\Config;

/**
 * Reads database credentials from config/database.php (env-backed — see
 * that file) and the RUNTIME driver (RoadRunner vs Swoole — a different
 * thing with a confusingly similar name) from spinx.json's "driver" key,
 * to decide which ConnectionManager implementation to build. Registered
 * as a container factory (see config/container.php) so application code
 * never constructs a connection manager directly.
 *
 * Defaults to SQLite with zero configuration required — matching the
 * "easy to install and spin up" goal from the build spec: a fresh
 * project can run migrations and start querying immediately, no
 * separate database server to install first. MySQL/Postgres are a
 * config change away, not a rewrite.
 */
final class ConnectionManagerFactory
{
    public function __construct(
        private readonly string $projectRoot,
    ) {
    }

    public function create(): ConnectionManager
    {
        $dbalParams = $this->buildDbalParams();
        $runtimeDriver = $this->readRuntimeDriver();

        return match ($runtimeDriver) {
            'swoole' => new SwooleConnectionManager($dbalParams),
            default => new RoadRunnerConnectionManager($dbalParams),
        };
    }

    /** spinx.json's "driver" — the HTTP runtime driver, not the database driver. */
    private function readRuntimeDriver(): string
    {
        $configFile = $this->projectRoot . '/spinx.json';

        if (!is_file($configFile)) {
            return 'roadrunner';
        }

        $config = json_decode((string) file_get_contents($configFile), true) ?? [];

        return $config['driver'] ?? 'roadrunner';
    }

    /** @return array<string, mixed> */
    private function buildDbalParams(): array
    {
        $driver = Config::instance()->get('database.driver', 'pdo_sqlite');

        if ($driver === 'pdo_sqlite') {
            $path = Config::instance()->get('database.path', 'storage/database.sqlite');
            $isAbsolute = str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;

            return [
                'driver' => 'pdo_sqlite',
                'path' => $isAbsolute ? $path : $this->projectRoot . '/' . $path,
            ];
        }

        return [
            'driver' => $driver,
            'host' => Config::instance()->get('database.host', '127.0.0.1'),
            'port' => Config::instance()->get('database.port'),
            'dbname' => Config::instance()->get('database.database'),
            'user' => Config::instance()->get('database.username'),
            'password' => Config::instance()->get('database.password'),
        ];
    }
}
