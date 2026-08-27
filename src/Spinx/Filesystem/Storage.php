<?php

declare(strict_types=1);

namespace Spinx\Filesystem;

use Spinx\Filesystem\Driver\FilesystemDriverInterface;

/**
 * Static facade for file storage and multi-disk operations.
 *
 * Usage:
 *   Storage::put('exports/book.pdf', $binary);
 *   $content = Storage::get('exports/book.pdf');
 *   Storage::disk('s3')->put('uploads/photo.jpg', $stream);
 *   $url = Storage::disk('s3')->temporaryUrl('uploads/photo.jpg', now()->addHour());
 */
final class Storage
{
    private static ?FilesystemManager $manager = null;

    public static function setManager(FilesystemManager $manager): void
    {
        self::$manager = $manager;
    }

    public static function getManager(): FilesystemManager
    {
        if (self::$manager === null) {
            self::$manager = new FilesystemManager();
        }

        return self::$manager;
    }

    public static function disk(?string $name = null): FilesystemDriverInterface
    {
        return self::getManager()->disk($name);
    }

    public static function put(string $path, mixed $contents, array $options = []): bool
    {
        return self::getManager()->disk()->put($path, $contents, $options);
    }

    public static function get(string $path): ?string
    {
        return self::getManager()->disk()->get($path);
    }

    public static function exists(string $path): bool
    {
        return self::getManager()->disk()->exists($path);
    }

    public static function delete(string|array $paths): bool
    {
        return self::getManager()->disk()->delete($paths);
    }

    public static function url(string $path): string
    {
        return self::getManager()->disk()->url($path);
    }

    public static function temporaryUrl(string $path, \DateTimeInterface $expiration, array $options = []): string
    {
        return self::getManager()->disk()->temporaryUrl($path, $expiration, $options);
    }

    public static function files(string $directory = '', bool $recursive = false): array
    {
        return self::getManager()->disk()->files($directory, $recursive);
    }

    public static function __callStatic(string $method, array $arguments): mixed
    {
        return self::getManager()->disk()->$method(...$arguments);
    }
}
