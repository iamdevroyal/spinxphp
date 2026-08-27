<?php

declare(strict_types=1);

namespace Spinx\Filesystem;

use Spinx\Filesystem\Driver\FilesystemDriverInterface;
use Spinx\Filesystem\Driver\LocalFilesystemDriver;
use Spinx\Filesystem\Driver\S3FilesystemDriver;
use Spinx\Support\Config;

/**
 * Filesystem manager managing storage disks and drivers.
 */
final class FilesystemManager
{
    /** @var array<string, FilesystemDriverInterface> */
    private array $disks = [];

    public function __construct(
        private readonly ?string $defaultDisk = null,
    ) {
    }

    public function disk(?string $name = null): FilesystemDriverInterface
    {
        $name = $name ?? $this->getDefaultDisk();

        return $this->disks[$name] ??= $this->resolve($name);
    }

    public function getDefaultDisk(): string
    {
        return $this->defaultDisk 
            ?? (string) Config::get('filesystem.default', env('FILESYSTEM_DISK', 'local'));
    }

    private function resolve(string $name): FilesystemDriverInterface
    {
        $config = (array) Config::get("filesystem.disks.{$name}", []);
        $driver = (string) ($config['driver'] ?? $name);

        return match ($driver) {
            'local' => new LocalFilesystemDriver(
                (string) ($config['root'] ?? storage_path('app')),
                (string) ($config['url'] ?? '')
            ),
            's3'    => new S3FilesystemDriver($config),
            default => throw new \InvalidArgumentException("Filesystem driver [{$driver}] is not supported."),
        };
    }

    public function __call(string $method, array $arguments): mixed
    {
        return $this->disk()->$method(...$arguments);
    }
}
