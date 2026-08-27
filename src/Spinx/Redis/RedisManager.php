<?php

declare(strict_types=1);

namespace Spinx\Redis;

use Spinx\Support\Config;

/**
 * Manages pooled/named Redis connections across Spinx subsystems (Cache, Session, Queue, RateLimit).
 */
final class RedisManager
{
    /** @var array<string, \Redis> */
    private array $connections = [];

    public function __construct(
        private readonly ?string $defaultConnection = null,
    ) {
    }

    /**
     * Get a named Redis client connection instance.
     */
    public function connection(?string $name = null): \Redis
    {
        $name = $name ?? $this->getDefaultConnection();

        return $this->connections[$name] ??= $this->resolve($name);
    }

    public function getDefaultConnection(): string
    {
        return $this->defaultConnection ?? 'default';
    }

    /**
     * Purge and disconnect all active Redis connections.
     */
    public function purge(?string $name = null): void
    {
        if ($name === null) {
            foreach ($this->connections as $conn) {
                try {
                    $conn->close();
                } catch (\Throwable) {
                }
            }
            $this->connections = [];
            return;
        }

        if (isset($this->connections[$name])) {
            try {
                $this->connections[$name]->close();
            } catch (\Throwable) {
            }
            unset($this->connections[$name]);
        }
    }

    private function resolve(string $name): \Redis
    {
        if (!extension_loaded('redis')) {
            throw new \RuntimeException('The "redis" PHP extension (phpredis) is required to use Redis connections.');
        }

        $config = Config::get("redis.connections.{$name}", Config::get("redis.{$name}"));

        if (!is_array($config)) {
            // Fallback to default connection parameters
            $config = [
                'host'     => (string) Config::get('redis.host', env('REDIS_HOST', '127.0.0.1')),
                'port'     => (int) Config::get('redis.port', env('REDIS_PORT', 6379)),
                'password' => Config::get('redis.password', env('REDIS_PASSWORD', null)),
                'database' => (int) Config::get('redis.database', env('REDIS_DB', 0)),
                'timeout'  => (float) Config::get('redis.timeout', 2.0),
            ];
        }

        $host     = (string) ($config['host'] ?? '127.0.0.1');
        $port     = (int) ($config['port'] ?? 6379);
        $timeout  = (float) ($config['timeout'] ?? 2.0);
        $password = $config['password'] ?? Config::get('redis.password', env('REDIS_PASSWORD', null));
        $database = (int) ($config['database'] ?? 0);

        $client = new \Redis();

        $connected = $client->connect($host, $port, $timeout);
        if (!$connected) {
            throw new \RuntimeException("Unable to connect to Redis server at [{$host}:{$port}].");
        }

        if ($password !== null && $password !== '') {
            $client->auth($password);
        }

        if ($database > 0) {
            $client->select($database);
        }

        return $client;
    }

    public function __call(string $method, array $arguments): mixed
    {
        return $this->connection()->$method(...$arguments);
    }
}
