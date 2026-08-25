<?php

declare(strict_types=1);

namespace Spinx\Session;

/**
 * File-backed session — stores each session as a JSON file in
 * storage/sessions/{id}.json. Suitable for single-server development
 * and low-traffic production. Switch to DatabaseSession for multi-server
 * setups (see config/session.php 'driver').
 *
 * Coroutine safety: each request owns its own session object and a unique
 * session ID, so two concurrent coroutines in Swoole can never touch the
 * same file without a conflict. The only shared resource is the sessions
 * directory, and directory-level contention (scandir, etc.) is not an
 * issue here since we read/write by known filename only.
 *
 * This class holds in-memory state only for the duration of a single
 * request. SessionMiddleware calls hydrate() at request start and
 * reads all() + getId() at request end to persist the session back.
 * Between requests the instance is discarded by the request scope reset.
 */
final class FileSession implements SessionInterface
{
    private string $sessionId   = '';
    /** @var array<string, mixed> */
    private array $data         = [];
    private string $storageDir  = '';

    public function __construct(string $storageDir)
    {
        $this->storageDir = rtrim($storageDir, '/\\');
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
        $oldPath = $this->filePath($this->sessionId);

        $this->sessionId = $this->generateId();
        $newPath         = $this->filePath($this->sessionId);

        // Move data to the new ID — the old file will be left to expire
        // naturally (GC or TTL-based cleanup via a scheduled task).
        if (is_file($oldPath)) {
            rename($oldPath, $newPath);
        }
    }

    public function all(): array
    {
        return $this->data;
    }

    public function hydrate(string $id, array $data): void
    {
        $this->sessionId = $id;
        $this->data      = $data;
    }

    // ---------------------------------------------------------------
    // Static helpers called by SessionMiddleware
    // ---------------------------------------------------------------

    /**
     * Reads session data from storage for the given $id.
     * Returns an empty array if the file doesn't exist (new session or expired).
     *
     * @return array<string, mixed>
     */
    public function load(string $id): array
    {
        $path = $this->filePath($id);

        if (!is_file($path)) {
            return [];
        }

        $raw = file_get_contents($path);

        if ($raw === false || $raw === '') {
            return [];
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : [];
        } catch (\JsonException) {
            return [];
        }
    }

    /**
     * Writes current session data back to storage.
     * Called by SessionMiddleware after the response is generated.
     */
    public function persist(): void
    {
        if ($this->sessionId === '') {
            return;
        }

        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0755, true);
        }

        file_put_contents(
            $this->filePath($this->sessionId),
            json_encode($this->data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }

    private function filePath(string $id): string
    {
        return $this->storageDir . '/' . $id . '.json';
    }

    private function generateId(): string
    {
        return bin2hex(random_bytes(32));
    }
}
