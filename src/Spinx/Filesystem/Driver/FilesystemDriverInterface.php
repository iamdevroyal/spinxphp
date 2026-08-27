<?php

declare(strict_types=1);

namespace Spinx\Filesystem\Driver;

/**
 * Universal contract for Spinx filesystem and object storage drivers.
 */
interface FilesystemDriverInterface
{
    /**
     * Write contents to a file.
     *
     * @param string $path
     * @param string|resource $contents
     * @param array<string, mixed> $options
     */
    public function put(string $path, mixed $contents, array $options = []): bool;

    /**
     * Read the contents of a file.
     */
    public function get(string $path): ?string;

    /**
     * Check if a file exists.
     */
    public function exists(string $path): bool;

    /**
     * Delete one or more files.
     *
     * @param string|string[] $paths
     */
    public function delete(string|array $paths): bool;

    /**
     * Copy a file to a new location.
     */
    public function copy(string $from, string $to): bool;

    /**
     * Move / rename a file to a new location.
     */
    public function move(string $from, string $to): bool;

    /**
     * Get the file size in bytes.
     */
    public function size(string $path): int;

    /**
     * Get the file's last modified timestamp.
     */
    public function lastModified(string $path): int;

    /**
     * Get the public URL for a file.
     */
    public function url(string $path): string;

    /**
     * Generate a temporary signed download URL for a file.
     */
    public function temporaryUrl(string $path, \DateTimeInterface $expiration, array $options = []): string;

    /**
     * List all files in a directory.
     *
     * @return string[]
     */
    public function files(string $directory = '', bool $recursive = false): array;

    /**
     * Create a directory.
     */
    public function makeDirectory(string $path): bool;

    /**
     * Recursively delete a directory.
     */
    public function deleteDirectory(string $path): bool;
}
