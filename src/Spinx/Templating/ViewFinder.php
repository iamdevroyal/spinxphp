<?php

declare(strict_types=1);

namespace Spinx\Templating;

/**
 * Resolves a view name to an absolute .spinx.html path.
 *
 * Two forms:
 *   'welcome'            -> resources/views/welcome.spinx.html
 *   'layouts.app'        -> resources/views/layouts/app.spinx.html
 *   'Orders::checkout'   -> app/Modules/Orders/Infrastructure/Http/Views/checkout.spinx.html
 *
 * Module-qualified views keep presentation templates inside the owning
 * module's Infrastructure layer (consistent with build spec §5 — views
 * are a presentation concern, which belongs in Infrastructure just like
 * controllers). Unqualified views are for shared/global templates
 * (layouts, error pages) that don't belong to any single module.
 */
final class ViewFinder
{
    public function __construct(
        private readonly string $projectRoot,
    ) {
    }

    public function resolve(string $view): string
    {
        $path = str_contains($view, '::')
            ? $this->resolveModuleView($view)
            : $this->resolveGlobalView($view);

        if (!is_file($path)) {
            throw new \RuntimeException(sprintf('View "%s" not found. Looked for: %s', $view, $path));
        }

        return $path;
    }

    private function resolveModuleView(string $view): string
    {
        [$module, $name] = explode('::', $view, 2);

        return $this->projectRoot
            . '/app/Modules/' . $module . '/Infrastructure/Http/Views/'
            . str_replace('.', '/', $name) . '.spinx.html';
    }

    private function resolveGlobalView(string $view): string
    {
        return $this->projectRoot . '/resources/views/' . str_replace('.', '/', $view) . '.spinx.html';
    }
}
