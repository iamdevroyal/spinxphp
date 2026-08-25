<?php

declare(strict_types=1);

namespace Spinx\Generator;

/**
 * Backs `spinx new <project> [--frontend=vue|react]` — closes a real gap
 * left open through most of this framework's build: there was no way to
 * start a brand new project, only to keep extending this one reference
 * repo. Given Spinx isn't published as a separate framework/skeleton
 * pair on Packagist (this repo contains both the framework source and a
 * reference app together), the honest, working implementation is: copy
 * this installation's own framework skeleton into a new directory,
 * strip out everything specific to THIS repo (the Health/Todo reference
 * modules, this build's own spec/history docs), and leave a genuinely
 * blank slate — an empty app/Modules/, a fresh spinx.json, a copied
 * .env.example promoted to .env so the new project runs immediately.
 */
final class ProjectGenerator
{
    /** Copied as-is into every new project. */
    private const ALWAYS_COPY = [
        'src',
        'spinx',
        'config',
        'public',
        'database',
        'tools',
        'docs',
        'composer.json',
        'phpstan.neon',
        '.rr.yaml',
        'Dockerfile',
        '.dockerignore',
        '.gitignore',
        '.env.example',
    ];

    public function __construct(
        private readonly string $frameworkRoot,
    ) {
    }

    /** @return array{targetDir: string, frontend: string} */
    public function generate(string $targetDir, string $frontend = 'vue'): array
    {
        if (is_dir($targetDir)) {
            throw new \RuntimeException("Target directory already exists: {$targetDir}");
        }

        if (!in_array($frontend, ['vue', 'react'], true)) {
            throw new \InvalidArgumentException('Frontend must be "vue" or "react".');
        }

        mkdir($targetDir, 0755, true);

        foreach (self::ALWAYS_COPY as $item) {
            $source = $this->frameworkRoot . '/' . $item;

            if (!file_exists($source)) {
                continue; // Some items (e.g. docs/) are optional depending on what's in the source install.
            }

            $this->copyRecursive($source, $targetDir . '/' . $item);
        }

        // Frontend: Vue's own scaffold, or the React alternative promoted to frontend/.
        $frontendSource = $frontend === 'react'
            ? $this->frameworkRoot . '/examples/react-frontend'
            : $this->frameworkRoot . '/frontend';

        if (is_dir($frontendSource)) {
            $this->copyRecursive($frontendSource, $targetDir . '/frontend', excludeDirs: ['node_modules', 'dist']);
        }

        // Blank slate: no reference modules, empty registry.
        mkdir($targetDir . '/app/Modules', 0755, true);
        file_put_contents($targetDir . '/spinx.json', json_encode([
            'driver' => 'roadrunner',
            'frontend' => $frontend,
            'swoole' => ['host' => '0.0.0.0', 'port' => 9501, 'workers' => 4],
            'modules' => new \stdClass(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

        // Storage skeleton (empty structure, no cache contents or DB file).
        mkdir($targetDir . '/storage/cache/views', 0755, true);
        mkdir($targetDir . '/storage/frontend', 0755, true);
        touch($targetDir . '/storage/cache/.gitkeep');

        // .env ready to go immediately — .env.example was already copied above.
        copy($targetDir . '/.env.example', $targetDir . '/.env');

        // copy() doesn't reliably preserve the executable bit — without
        // this, a fresh project's `php spinx serve` still works (invoked
        // via the `php` interpreter explicitly), but `./spinx serve`
        // would fail with a permission error, which is surprising for
        // something meant to feel like Laravel's `php artisan`.
        chmod($targetDir . '/spinx', 0755);

        $projectName = basename($targetDir);
        file_put_contents($targetDir . '/README.md', $this->freshReadme($projectName));

        return ['targetDir' => $targetDir, 'frontend' => $frontend];
    }

    /** @param string[] $excludeDirs */
    private function copyRecursive(string $source, string $destination, array $excludeDirs = []): void
    {
        if (is_file($source)) {
            $this->ensureDirExists(dirname($destination));
            copy($source, $destination);

            return;
        }

        $this->ensureDirExists($destination);

        foreach (scandir($source) ?: [] as $item) {
            if ($item === '.' || $item === '..' || in_array($item, $excludeDirs, true)) {
                continue;
            }

            $this->copyRecursive($source . '/' . $item, $destination . '/' . $item, $excludeDirs);
        }
    }

    private function ensureDirExists(string $dir): void
    {
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException("Failed to create directory: {$dir}");
        }
    }

    private function freshReadme(string $projectName): string
    {
        return <<<MD
            # {$projectName}

            A Spinx app. See `docs/getting-started.md` for the full guide.

            ## Setup

            ```bash
            composer install
            cd frontend && npm install && cd ..
            vendor/bin/rr get
            php spinx migrate
            php spinx serve
            ```

            ## Your first module

            ```bash
            php spinx make:module Orders --all
            ```
            MD;
    }
}
