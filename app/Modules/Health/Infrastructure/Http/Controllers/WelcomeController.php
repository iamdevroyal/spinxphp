<?php

declare(strict_types=1);

namespace App\Modules\Health\Infrastructure\Http\Controllers;

use Spinx\Templating\TemplateRenderer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reference controller proving the templating pipeline from build step 4
 * works end to end: view resolution, directive compilation, caching, and
 * Vue island hydration hooks all exercised by a single request.
 */
final class WelcomeController
{
    public function __construct(
        private readonly TemplateRenderer $renderer,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $html = $this->renderer->render('welcome', [
            'title' => 'Spinx',
            'showIntro' => true,
            'features' => [
                'RoadRunner runtime, near-Node persistent-process speed',
                'Enforced DDD modules — no bare app/Controllers fallback',
                'Vue islands with HMR via the @vite directive',
            ],
            'islandMessage' => 'Hydrated client-side via data-spinx-island',
        ]);

        return new Response($html);
    }
}
