<?php

declare(strict_types=1);

namespace Spinx\Filesystem\Driver;

/**
 * Local server filesystem driver.
 */
final class LocalFilesystemDriver implements FilesystemDriverInterface
{
    private string $root;
    private string $urlBase;

    public function __construct(
        string $root = '',
        string $urlBase = '',
    ) {
        $this->root = $root !== '' ? rtrim($root, '/\\') : storage_path('app');
        $this->urlBase = rtrim($urlBase, '/');
    }

    public function put(string $path, mixed $contents, array $options = []): bool
    {
        $fullPath = $this->fullPath($path);
        $dir = dirname($fullPath);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (is_resource($contents)) {
            $stream = fopen($fullPath, 'w');
            if ($stream === false) {
                return false;
            }
            stream_copy_to_stream($contents, $stream);
            fclose($stream);
            return true;
        }

        return file_put_contents($fullPath, (string) $contents, LOCK_EX) !== false;
    }

    public function get(string $path): ?string
    {
        $fullPath = $this->fullPath($path);

        if (!is_file($fullPath)) {
            return null;
        }

        $content = file_get_contents($fullPath);
        return $content !== false ? $content : null;
    }

    public function exists(string $path): bool
    {
        return file_exists($this->fullPath($path));
    }

    public function delete(string|array $paths): bool
    {
        $paths = is_array($paths) ? $paths : [$paths];
        $allSuccess = true;

        foreach ($paths as $path) {
            $fullPath = $this->fullPath($path);
            if (is_file($fullPath)) {
                $allSuccess = unlink($fullPath) && $allSuccess;
            }
        }

        return $allSuccess;
    }

    public function copy(string $from, string $to): bool
    {
        $src = $this->fullPath($from);
        $dst = $this->fullPath($to);

        $dir = dirname($dst);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return is_file($src) && copy($src, $dst);
    }

    public function move(string $from, string $to): bool
    {
        $src = $this->fullPath($from);
        $dst = $this->fullPath($to);

        $dir = dirname($dst);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return is_file($src) && rename($src, $dst);
    }

    public function size(string $path): int
    {
        $fullPath = $this->fullPath($path);
        return is_file($fullPath) ? (int) filesize($fullPath) : 0;
    }

    public function lastModified(string $path): int
    {
        $fullPath = $this->fullPath($path);
        return is_file($fullPath) ? (int) filemtime($fullPath) : 0;
    }

    public function url(string $path): string
    {
        if ($this->urlBase !== '') {
            return $this->urlBase . '/' . ltrim($path, '/');
        }

        return '/storage/' . ltrim($path, '/');
    }

    public function temporaryUrl(string $path, \DateTimeInterface $expiration, array $options = []): string
    {
        $expires = $expiration->getTimestamp();
        $hash = hash_hmac('sha256', "{$path}:{$expires}", (string) env('APP_KEY', 'spinx-secret'));

        return $this->url($path) . "?expires={$expires}&signature={$hash}";
    }

    public function files(string $directory = '', bool $recursive = false): array
    {
        $dir = $this->fullPath($directory);
        if (!is_dir($dir)) {
            return [];
        }

        $results = [];
        $items = scandir($dir) ?: [];

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $dir . '/' . $item;
            $relPath = ($directory !== '' ? rtrim($directory, '/') . '/' : '') . $item;

            if (is_file($itemPath)) {
                $results[] = $relPath;
            } elseif ($recursive && is_dir($itemPath)) {
                $results = array_merge($results, $this->files($relPath, true));
            }
        }

        return $results;
    }

    public function makeDirectory(string $path): bool
    {
        $dir = $this->fullPath($path);
        return is_dir($dir) || mkdir($dir, 0755, true);
    }

    public function deleteDirectory(string $path): bool
    {
        $dir = $this->fullPath($path);
        if (!is_dir($dir)) {
            return true;
        }

        $files = array_diff(scandir($dir) ?: [], ['.', '..']);
        foreach ($files as $file) {
            $item = $dir . '/' . $file;
            is_dir($item) ? $this->deleteDirectory($path . '/' . $file) : unlink($item);
        }

        return rmdir($dir);
    }

    private function fullPath(string $path): string
    {
        // 1. Strip null bytes
        $clean = str_replace("\0", '', $path);

        // 2. Normalize backslashes to forward slashes
        $normalized = str_replace('\\', '/', $clean);

        // 3. Inspect segments for directory traversal sequences
        $segments = explode('/', $normalized);
        $safeSegments = [];

        foreach ($segments as $segment) {
            $segment = trim($segment);
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                throw new \InvalidArgumentException("Directory traversal attempt detected in path [{$path}].");
            }
            $safeSegments[] = $segment;
        }

        $relative = implode('/', $safeSegments);
        $fullPath = $this->root . ($relative !== '' ? '/' . $relative : '');

        return $fullPath;
    }
}
