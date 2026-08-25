<?php

declare(strict_types=1);

namespace Spinx\Cache\Store;

/**
 * File-based cache store with expiration timestamps and atomic file writes.
 */
final class FileCacheStore implements CacheStoreInterface
{
    public function __construct(
        private readonly string $directory,
    ) {
        if (!is_dir($this->directory)) {
            @mkdir($this->directory, 0775, true);
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $path = $this->path($key);

        if (!is_file($path)) {
            return $default;
        }

        $contents = @file_get_contents($path);
        if ($contents === false || $contents === '') {
            return $default;
        }

        $payload = @unserialize($contents);
        if (!is_array($payload) || !isset($payload['expires_at'])) {
            @unlink($path);
            return $default;
        }

        if ($payload['expires_at'] !== 0 && time() >= $payload['expires_at']) {
            @unlink($path);
            return $default;
        }

        return $payload['value'] ?? $default;
    }

    public function put(string $key, mixed $value, int|\DateInterval|null $ttl = null): bool
    {
        $expiresAt = $this->calculateExpiration($ttl);
        $payload = serialize([
            'expires_at' => $expiresAt,
            'value'      => $value,
        ]);

        $path = $this->path($key);
        $dir = dirname($path);

        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $tempPath = $path . '.' . bin2hex(random_bytes(6)) . '.tmp';
        if (@file_put_contents($tempPath, $payload) === false) {
            return false;
        }

        return @rename($tempPath, $path);
    }

    public function has(string $key): bool
    {
        return $this->get($key, $this) !== $this;
    }

    public function forget(string $key): bool
    {
        $path = $this->path($key);

        if (is_file($path)) {
            return @unlink($path);
        }

        return true;
    }

    public function flush(): bool
    {
        if (!is_dir($this->directory)) {
            return true;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            if ($file->isDir()) {
                @rmdir($file->getRealPath());
            } else {
                @unlink($file->getRealPath());
            }
        }

        return true;
    }

    public function increment(string $key, int $value = 1): int|bool
    {
        $current = $this->get($key, 0);
        if (!is_numeric($current)) {
            return false;
        }

        $new = (int) $current + $value;
        $this->put($key, $new);

        return $new;
    }

    public function decrement(string $key, int $value = 1): int|bool
    {
        return $this->increment($key, -$value);
    }

    public function remember(string $key, int|\DateInterval|null $ttl, \Closure $callback): mixed
    {
        $val = $this->get($key, $this);

        if ($val !== $this) {
            return $val;
        }

        $result = $callback();
        $this->put($key, $result, $ttl);

        return $result;
    }

    public function getDirectory(): string
    {
        return $this->directory;
    }

    private function path(string $key): string
    {
        $hash = sha1($key);
        $parts = array_slice(str_split($hash, 2), 0, 2);

        return $this->directory . '/' . implode('/', $parts) . '/' . $hash;
    }

    private function calculateExpiration(int|\DateInterval|null $ttl): int
    {
        if ($ttl === null || $ttl === 0) {
            return 0; // 0 = never expires
        }

        if ($ttl instanceof \DateInterval) {
            return (new \DateTimeImmutable())->add($ttl)->getTimestamp();
        }

        return time() + $ttl;
    }
}
