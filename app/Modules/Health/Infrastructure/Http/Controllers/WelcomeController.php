<?php

declare(strict_types=1);

namespace App\Modules\Health\Infrastructure\Http\Controllers;

use Spinx\Support\Config;
use Spinx\Templating\TemplateRenderer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Default Welcome Controller greeting developers upon `spinx serve`.
 * Passes real-time system diagnostics, PHP version, active runtime driver,
 * and island hydration data to the welcome view.
 */
final class WelcomeController
{
    public function __construct(
        private readonly TemplateRenderer $renderer,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $driver = Config::get('app.driver', 'RoadRunner (Persistent)');
        $frontend = Config::get('app.frontend', 'Vue 3 + Vite');
        $env = Config::get('app.env', 'local');
        $modules = Config::get('modules', ['Health' => true, 'Todo' => true]);

        $html = $this->renderer->render('welcome', [
            'title'        => 'Spinx Framework',
            'spinxVersion' => '1.0.0',
            'phpVersion'   => PHP_VERSION,
            'driver'       => is_string($driver) ? ucfirst($driver) : 'RoadRunner (Persistent)',
            'frontend'     => is_string($frontend) ? ucfirst($frontend) : 'Vue 3',
            'env'          => is_string($env) ? ucfirst($env) : 'Local',
            'modulesCount' => is_array($modules) ? count($modules) : 2,
            'docsUrl'      => 'https://spinx.dev/docs',
            'repoUrl'      => 'https://github.com/iamdevroyal/spinxphp',
        ]);

        return new Response($html);
    }
}
