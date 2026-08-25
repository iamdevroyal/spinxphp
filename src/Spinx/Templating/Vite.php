<?php

declare(strict_types=1);

namespace Spinx\Templating;

/**
 * Backs the @vite directive. Follows the same proven pattern Laravel's
 * Vite integration uses (rather than a literal single-port reverse proxy,
 * which would add a real latency/complexity cost for no functional gain):
 *
 * - In dev mode, `spinx serve` boots the Vite dev server alongside
 *   RoadRunner/Swoole and drops a marker file at storage/frontend/hot containing
 *   the dev server's URL. When that file exists, @vite emits script tags
 *   pointing directly at the Vite dev server, giving full HMR.
 * - In production, storage/frontend/hot won't exist (nothing wrote it),
 *   so @vite falls back to reading public/build/.vite/manifest.json — the
 *   compiled, hashed asset filenames Vite produces on `spinx build`.
 */
final class Vite
{
    public function __construct(
        private readonly string $projectRoot,
    ) {
    }

    public function tags(): string
    {
        $hotFile = $this->projectRoot . '/storage/frontend/hot';

        if (is_file($hotFile)) {
            return $this->devTags((string) file_get_contents($hotFile));
        }

        return $this->productionTags();
    }

    private function devTags(string $devServerUrl): string
    {
        $devServerUrl = rtrim($devServerUrl);
        $entry = $this->resolveEntryScript();

        return sprintf(
            '<script type="module" src="%1$s/@vite/client"></script>' . "\n" .
            '<script type="module" src="%1$s/%2$s"></script>',
            htmlspecialchars($devServerUrl, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($entry, ENT_QUOTES, 'UTF-8')
        );
    }

    private function resolveEntryScript(): string
    {
        if (is_file($this->projectRoot . '/frontend/src/main.jsx')) {
            return 'src/main.jsx';
        }

        return 'src/main.js';
    }

    private function productionTags(): string
    {
        // Vite 5 writes the manifest to public/build/.vite/manifest.json
        $manifestPath = $this->projectRoot . '/public/build/.vite/manifest.json';

        if (!is_file($manifestPath)) {
            return '<!-- Spinx: no frontend build found. Run `spinx serve` for dev, or `spinx build` for production. -->';
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true) ?? [];

        // Entry key matches whichever frontend is configured — Vue's
        // default scaffold uses src/main.js, the React alternative uses src/main.jsx.
        $entry = $manifest['src/main.js'] ?? $manifest['src/main.jsx'] ?? null;

        if ($entry === null) {
            return '<!-- Spinx: frontend entry missing from Vite manifest. -->';
        }

        $tags = sprintf('<script type="module" src="/build/%s"></script>', htmlspecialchars($entry['file'], ENT_QUOTES, 'UTF-8'));

        foreach ($entry['css'] ?? [] as $cssFile) {
            $tags .= sprintf('<link rel="stylesheet" href="/build/%s">', htmlspecialchars($cssFile, ENT_QUOTES, 'UTF-8'));
        }

        return $tags;
    }
}
