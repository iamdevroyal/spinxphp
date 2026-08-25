<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Http\Controllers;

use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use Spinx\Auth\Auth;
use Spinx\Auth\Hash;
use Spinx\Templating\TemplateRenderer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class RegisterSubmitController
{
    public function __construct(
        private readonly TemplateRenderer $renderer,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $name = trim((string) $request->request->get('name'));
        $email = strtolower(trim((string) $request->request->get('email')));
        $password = (string) $request->request->get('password');
        $passwordConfirm = (string) $request->request->get('password_confirmation');

        if ($name === '' || $email === '' || $password === '') {
            return $this->renderError('All fields are required.', $name, $email, 422);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->renderError('Please enter a valid email address.', $name, $email, 422);
        }

        if (strlen($password) < 6) {
            return $this->renderError('Password must be at least 6 characters.', $name, $email, 422);
        }

        if ($password !== $passwordConfirm) {
            return $this->renderError('Passwords do not match.', $name, $email, 422);
        }

        $existing = User::query()->where('email', '=', $email)->first();
        if ($existing !== null) {
            return $this->renderError('An account with this email already exists.', $name, $email, 422);
        }

        $user = User::create([
            'name'     => $name,
            'email'    => $email,
            'password' => Hash::make($password),
        ]);

        Auth::login($user);

        return redirect('/dashboard');
    }

    private function renderError(string $message, string $name, string $email, int $status): Response
    {
        $html = $this->renderer->render('Auth::register', [
            'title' => 'Create Account — Spinx App',
            'error' => $message,
            'name'  => $name,
            'email' => $email,
        ]);

        return new Response($html, $status);
    }
}
