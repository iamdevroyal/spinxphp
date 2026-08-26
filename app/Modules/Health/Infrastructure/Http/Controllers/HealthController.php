<?php

declare(strict_types=1);

namespace App\Modules\Health\Infrastructure\Http\Controllers;

use App\Modules\Health\Infrastructure\Persistence\Models\HealthCheckLog;
use Spinx\Http\Response;
use Spinx\Support\Config;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Unified Health & Welcome Controller.
 *
 * Uses Response and view() facades exclusively — no raw Symfony Response imports needed.
 */
final class HealthController
{
    /**
     * Display the framework welcome screen.
     * GET /
     */
    public function welcome(): SymfonyResponse
    {
        $spinxConfig = @json_decode((string) @file_get_contents(base_path('spinx.json')), true) ?? [];
        $driver      = $spinxConfig['driver']   ?? env('SPINX_DRIVER', 'roadrunner');
        $frontend    = $spinxConfig['frontend'] ?? 'vue';
        $driverLabel   = strtolower((string) $driver)   === 'swoole' ? 'Swoole (Coroutines)'     : 'RoadRunner (Persistent)';
        $frontendLabel = strtolower((string) $frontend) === 'react'  ? 'React 19'                : 'Vue 3';
        $env     = Config::get('app.env', env('APP_ENV', 'local'));
        $modules = $spinxConfig['modules'] ?? ['Health' => true, 'Todo' => true, 'Auth' => true];

        return view('welcome', [
            'title'        => 'Spinx Framework',
            'spinxVersion' => \Spinx\Kernel\Kernel::VERSION,
            'phpVersion'   => PHP_VERSION,
            'driver'       => $driverLabel,
            'frontend'     => $frontendLabel,
            'env'          => ucfirst((string) $env),
            'modulesCount' => is_array($modules) ? count($modules) : 3,
            'docsUrl'      => 'https://spinxphp.pages.dev/docs',
            'repoUrl'      => 'https://github.com/iamdevroyal/spinxphp',
        ]);
    }

    /**
     * API health check endpoint.
     * GET /health
     */
    public function check(): SymfonyResponse
    {
        HealthCheckLog::create(['status' => 'ok']);

        return response([
            'status'  => 'ok',
            'version' => \Spinx\Kernel\Kernel::VERSION,
            'module'  => 'Health',
        ]);
    }
}
