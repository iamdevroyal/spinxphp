<?php

declare(strict_types=1);

namespace Spinx\Session;

use Spinx\Redis\RedisManager;
use Spinx\Support\Config;

/**
 * Redis-backed session driver — stores session data in Redis with native key TTLs.
 * Highly recommended for persistent worker runtimes (RoadRunner, Swoole) and
 * horizontally scaled multi-server environments.
 */
final class RedisSession implements SessionInterface
{
    private string $sessionId = '';
    /** @var array<string, mixed> */
    private array $data = [];
    private string $prefix;
    private int $lifetime;

    public function __construct(
        private readonly ?RedisManager $redis = null,
        ?string $prefix = null,
        ?int $lifetimeMinutes = null,
    ) {
        $this->prefix = $prefix ?? (string) Config::get('session.redis.prefix', 'spinx_session:');
        $this->lifetime = ($lifetimeMinutes ?? (int) Config::get('session.lifetime', 120)) * 60;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function has(string $key): bool
    {
        return isset($this->data[$key]);
    }

    public function forget(string $key): void
    {
        unset($this->data[$key]);
    }

    public function flush(): void
    {
        $this->data = [];
    }

    public function getId(): string
    {
        return $this->sessionId;
    }

    public function regenerate(): void
    {
        $oldId = $this->sessionId;
        $this->sessionId = bin2hex(random_bytes(32));

        if ($oldId !== '') {
            try {
                $client = $this->getClient();
                $client->del($this->prefix . $oldId);
            } catch (\Throwable) {
            }
        }
    }

    public function all(): array
    {
        return $this->data;
    }

    public function hydrate(string $id, array $data): void
    {
        $this->sessionId = $id;
        $this->data = $data;
    }

    public function load(string $id): array
    {
        try {
            $client = $this->getClient();
            $raw = $client->get($this->prefix . $id);

            if ($raw === false || $raw === null || $raw === '') {
                return [];
            }

            $decoded = json_decode((string) $raw, true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable) {
            return [];
        }
    }

    public function persist(): void
    {
        if ($this->sessionId === '') {
            return;
        }

        try {
            $client = $this->getClient();
            $payload = json_encode($this->data, JSON_THROW_ON_ERROR);

            if ($this->lifetime > 0) {
                $client->setex($this->prefix . $this->sessionId, $this->lifetime, $payload);
            } else {
                $client->set($this->prefix . $this->sessionId, $payload);
            }
        } catch (\Throwable) {
        }
    }

    private function getClient(): \Redis
    {
        if ($this->redis !== null) {
            return $this->redis->connection('session');
        }

        return \Spinx\Redis\Redis::connection('session');
    }
}
