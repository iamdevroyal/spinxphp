<?php

declare(strict_types=1);

namespace Spinx\Templating;

/**
 * Backs the @vite directive. Follows the same proven pattern Laravel's
 * Vite integration uses (rather than a literal single-port reverse proxy,
 * which would add a real latency/complexity cost for no functional gain):
 *
 * - In dev mode, `spinx serve` boots the Vite dev server alongside
 *   RoadRunner and drops a marker file at storage/frontend/hot containing
 *   the dev server's URL. When that file exists, @vite emits script tags
 *   pointing directly at the Vite dev server, giving full HMR.
 * - In production, storage/frontend/hot won't exist (nothing wrote it),
 *   so @vite falls back to reading public/build/manifest.json — the
 *   compiled, hashed asset filenames Vite produces on `spinx build`.
 *
 * The browser therefore always talks to Vite directly for JS/CSS in dev
 * mode and to static prebuilt assets in prod — only the initial HTML
 * request goes through the PHP backend either way, which is what "one
 * command boots both, HMR just works" needs to feel like from the
 * developer's side.
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

        return sprintf(
            '<script type="module" src="%1$s/@vite/client"></script>' . "\n" .
            '<script type="module" src="%1$s/src/main.js"></script>',
            htmlspecialchars($devServerUrl, ENT_QUOTES, 'UTF-8')
        );
    }

    private function productionTags(): string
    {
        // Vite 5 writes the manifest to public/build/.vite/manifest.json,
        // not public/build/manifest.json directly — confirmed by actually
        // running `vite build` (see examples/react-frontend, step 10)
        // rather than assumed from Vite's older pre-5 convention.
        $manifestPath = $this->projectRoot . '/public/build/.vite/manifest.json';

        if (!is_file($manifestPath)) {
            return '<!-- Spinx: no frontend build found. Run `spinx serve` for dev, or `spinx build` for production. -->';
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true) ?? [];

        // Entry key matches whichever frontend is configured — Vue's
        // default scaffold uses src/main.js, the React alternative
        // (examples/react-frontend) uses src/main.jsx.
        $entry = $manifest['src/main.js'] ?? $manifest['src/main.jsx'] ?? null;

        if ($entry === null) {
            return '<!-- Spinx: src/main.js entry missing from Vite manifest. -->';
        }

        $tags = sprintf('<script type="module" src="/build/%s"></script>', htmlspecialchars($entry['file'], ENT_QUOTES, 'UTF-8'));

        foreach ($entry['css'] ?? [] as $cssFile) {
            $tags .= sprintf('<link rel="stylesheet" href="/build/%s">', htmlspecialchars($cssFile, ENT_QUOTES, 'UTF-8'));
        }

        return $tags;
    }
}
