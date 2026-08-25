<?php

declare(strict_types=1);

namespace Spinx\Session;

use Spinx\Database\DB;

/**
 * Database-backed session — stores session data in the `spinx_sessions` table.
 * Suitable for multi-server deployments or horizontal scaling where
 * file sessions cannot be shared.
 */
final class DatabaseSession implements SessionInterface
{
    private string $sessionId = '';
    /** @var array<string, mixed> */
    private array $data = [];

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
                DB::statement('DELETE FROM spinx_sessions WHERE id = :id', ['id' => $oldId]);
            } catch (\Throwable) {
                // Table might not exist yet or connection issue
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
            $row = DB::selectOne(
                'SELECT payload, last_activity FROM spinx_sessions WHERE id = :id',
                ['id' => $id]
            );

            if ($row === null) {
                return [];
            }

            $lifetime = (int) (\Spinx\Support\Config::get('session.lifetime', 120)) * 60;
            if (time() - (int) $row['last_activity'] > $lifetime) {
                DB::statement('DELETE FROM spinx_sessions WHERE id = :id', ['id' => $id]);
                return [];
            }

            $decoded = json_decode((string) $row['payload'], true, 512, JSON_THROW_ON_ERROR);
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
            $payload = json_encode($this->data, JSON_THROW_ON_ERROR);
            $time = time();

            $existing = DB::selectOne('SELECT id FROM spinx_sessions WHERE id = :id', ['id' => $this->sessionId]);
            if ($existing !== null) {
                DB::statement(
                    'UPDATE spinx_sessions SET payload = :payload, last_activity = :time WHERE id = :id',
                    ['id' => $this->sessionId, 'payload' => $payload, 'time' => $time]
                );
            } else {
                DB::statement(
                    'INSERT INTO spinx_sessions (id, payload, last_activity) VALUES (:id, :payload, :time)',
                    ['id' => $this->sessionId, 'payload' => $payload, 'time' => $time]
                );
            }
        } catch (\Throwable) {
            // DB session table might not exist yet in early boot/testing
        }
    }
}
