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
        $spinxConfig = @json_decode((string) @file_get_contents(base_path('spinx.json')), true) ?? [];
        $driver = $spinxConfig['driver'] ?? env('SPINX_DRIVER', 'roadrunner');
        $frontend = $spinxConfig['frontend'] ?? 'vue';
        $driverLabel = strtolower((string) $driver) === 'swoole' ? 'Swoole (Coroutines)' : 'RoadRunner (Persistent)';
        $frontendLabel = strtolower((string) $frontend) === 'react' ? 'React 19' : 'Vue 3';
        $env = Config::get('app.env', env('APP_ENV', 'local'));
        $modules = $spinxConfig['modules'] ?? ['Health' => true, 'Todo' => true];

        $html = $this->renderer->render('welcome', [
            'title'        => 'Spinx Framework',
            'spinxVersion' => '1.0.4',
            'phpVersion'   => PHP_VERSION,
            'driver'       => $driverLabel,
            'frontend'     => $frontendLabel,
            'env'          => ucfirst((string) $env),
            'modulesCount' => is_array($modules) ? count($modules) : 2,
            'docsUrl'      => 'https://spinxphp.pages.dev/docs',
            'repoUrl'      => 'https://github.com/iamdevroyal/spinxphp',
        ]);

        return new Response($html);
    }
}
