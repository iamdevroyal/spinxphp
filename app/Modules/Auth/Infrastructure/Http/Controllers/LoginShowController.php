<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Http\Controllers;

use Spinx\Templating\TemplateRenderer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class LoginShowController
{
    public function __construct(
        private readonly TemplateRenderer $renderer,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $html = $this->renderer->render('Auth::login', [
            'title' => 'Sign In — Spinx App',
            'error' => null,
            'email' => '',
        ]);

        return new Response($html);
    }
}
