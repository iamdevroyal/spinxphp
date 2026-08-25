<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Http\Controllers;

use Spinx\Auth\Auth;
use Spinx\Templating\TemplateRenderer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class LoginSubmitController
{
    public function __construct(
        private readonly TemplateRenderer $renderer,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $email = trim((string) $request->request->get('email'));
        $password = (string) $request->request->get('password');

        if ($email === '' || $password === '') {
            $html = $this->renderer->render('Auth::login', [
                'title' => 'Sign In — Spinx App',
                'error' => 'Please enter both your email address and password.',
                'email' => $email,
            ]);

            return new Response($html, 422);
        }

        if (Auth::attempt(['email' => $email, 'password' => $password])) {
            return redirect('/dashboard');
        }

        $html = $this->renderer->render('Auth::login', [
            'title' => 'Sign In — Spinx App',
            'error' => 'Invalid email or password. Please try again.',
            'email' => $email,
        ]);

        return new Response($html, 401);
    }
}
