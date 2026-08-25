<?php

declare(strict_types=1);

namespace Spinx\Templating;

/**
 * The single entry point controllers use to render a view. Ties together
 * ViewFinder (name -> path), TemplateCache (path -> compiled PHP), and
 * Vite (asset tags for @vite) into one render(view, data) call.
 */
final class TemplateRenderer
{
    public function __construct(
        private readonly ViewFinder $finder,
        private readonly TemplateCache $cache,
        private readonly Vite $vite,
    ) {
    }

    /**
     * @param array<string, mixed> $data Passed into the template's scope as variables
     */
    public function render(string $view, array $data = []): string
    {
        $sourcePath = $this->finder->resolve($view);
        $compiledPath = $this->cache->getCompiledPath($sourcePath);

        // Bound as $__spinxRenderer so compiled @include calls can call
        // back into render() for partials, without polluting the
        // template's own variable scope with a generic name like $this.
        $__spinxRenderer = $this;

        extract($data, EXTR_SKIP);

        ob_start();
        include $compiledPath;

        return (string) ob_get_clean();
    }

    /** Called by the compiled output of the @vite directive. */
    public function vite(): string
    {
        return $this->vite->tags();
    }

    /** Called by the compiled output of the @csrf directive — emits a hidden form field carrying the current request's CSRF token. */
    public function csrfField(): string
    {
        $token = \Spinx\Security\Csrf::current();

        if ($token === null) {
            // CsrfMiddleware isn't attached to this route, so there's no
            // current-request token to embed. An empty field is safer
            // than throwing — plenty of routes render forms without
            // needing CSRF protection (e.g. a public search box), and
            // this shouldn't break rendering for those.
            return '';
        }

        return '<input type="hidden" name="_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }
}
