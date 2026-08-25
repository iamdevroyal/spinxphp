<?php

declare(strict_types=1);

namespace Spinx\Session;

/**
 * Request-scoped session contract.
 *
 * Implementations must be stateless between requests — each implementation
 * reads session data from an external store at the start of every request
 * (via SessionMiddleware) and writes it back at the end, then discards all
 * in-memory state. $_SESSION is never used (unsafe in RoadRunner/Swoole
 * persistent-process runtimes — see docs/auth.md §Sessions).
 */
interface SessionInterface
{
    /** Returns the value for $key, or $default if not set. */
    public function get(string $key, mixed $default = null): mixed;

    /** Stores $value under $key for the duration of this session. */
    public function set(string $key, mixed $value): void;

    /** Returns true if $key exists and is not null. */
    public function has(string $key): bool;

    /** Removes $key from the session. */
    public function forget(string $key): void;

    /** Clears all session data (but keeps the session ID). */
    public function flush(): void;

    /** Returns the current session ID. */
    public function getId(): string;

    /**
     * Generates a new session ID and migrates all existing data to it.
     * Must be called after login to prevent session-fixation attacks.
     */
    public function regenerate(): void;

    /**
     * Serializes the current session data so SessionMiddleware can persist it
     * to the backing store at the end of the request.
     *
     * @return array<string, mixed>
     */
    public function all(): array;

    /**
     * Replaces all session data with $data — called by SessionMiddleware at
     * request start after loading from the backing store.
     *
     * @param array<string, mixed> $data
     */
    public function hydrate(string $id, array $data): void;
}
