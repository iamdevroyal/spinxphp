<?php

declare(strict_types=1);

namespace Spinx\Templating;

/**
 * Compiles a .spinx.html source file to plain PHP exactly once, then
 * reuses the cached compiled file on every subsequent render — matching
 * Blade's caching model (build spec §6.1: "compiled templates are cached
 * like Blade's, not re-parsed per request"). This matters more here than
 * in a typical PHP-FPM app: on a persistent-process runtime the process
 * stays alive for many requests, so an uncached compile step would mean
 * re-parsing the same template thousands of times over the process's
 * lifetime instead of once.
 */
final class TemplateCache
{
    public function __construct(
        private readonly DirectiveCompiler $compiler,
        private readonly string $cacheDir,
    ) {
    }

    /**
     * Returns the absolute path to the compiled PHP file for a given
     * template source, recompiling only if the source is newer than the
     * cached file (or no cached file exists yet).
     */
    public function getCompiledPath(string $sourcePath): string
    {
        $viewCacheDir = rtrim($this->cacheDir, '/') . '/views';
        $compiledPath = $viewCacheDir . '/' . sha1($sourcePath) . '.php';

        $needsCompile = !is_file($compiledPath)
            || filemtime($compiledPath) < filemtime($sourcePath);

        if ($needsCompile) {
            if (!is_dir($viewCacheDir) && !mkdir($viewCacheDir, 0755, true) && !is_dir($viewCacheDir)) {
                throw new \RuntimeException("Failed to create view cache directory: {$viewCacheDir}");
            }

            $source = file_get_contents($sourcePath);
            if ($source === false) {
                throw new \RuntimeException("Failed to read template source: {$sourcePath}");
            }

            file_put_contents($compiledPath, $this->compiler->compile($source));
        }

        return $compiledPath;
    }
}
