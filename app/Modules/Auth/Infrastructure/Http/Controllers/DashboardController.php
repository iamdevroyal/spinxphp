<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Http\Controllers;

use Spinx\Auth\Auth;
use Spinx\Templating\TemplateRenderer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class DashboardController
{
    public function __construct(
        private readonly TemplateRenderer $renderer,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $user = Auth::user();

        $html = $this->renderer->render('Auth::dashboard', [
            'title'       => 'Dashboard — Spinx App',
            'user'        => $user,
            'phpVersion'  => PHP_VERSION,
            'spinxVersion'=> \Spinx\Kernel\Kernel::VERSION,
        ]);

        return new Response($html);
    }
}
