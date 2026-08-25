<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Http\Controllers;

use App\Modules\Auth\Application\Services\AuthService;
use Spinx\Http\Request;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response;

/**
 * Unified Auth Controller.
 *
 * In strict DDD architecture, this controller is ONLY responsible for:
 *   1. HTTP Request extraction and basic form validation
 *   2. Delegating business logic to Application Services (AuthService)
 *   3. Returning appropriate View templates or HTTP Responses.
 */
final class AuthController
{
    public function __construct(
        private readonly AuthService $authService,
    ) {
    }

    /**
     * Display the login view.
     * GET /login
     */
    public function showLogin(?SymfonyRequest $request = null): Response
    {
        return view('Auth::login', [
            'title' => 'Sign In — Spinx App',
            'error' => null,
            'email' => '',
        ]);
    }

    /**
     * Handle login authentication.
     * POST /login
     */
    public function login(?SymfonyRequest $request = null): Response
    {
        $email = trim((string) Request::input('email'));
        $password = (string) Request::input('password');

        if ($email === '' || $password === '') {
            return view('Auth::login', [
                'title' => 'Sign In — Spinx App',
                'error' => 'Please enter both your email address and password.',
                'email' => $email,
            ], 422);
        }

        if ($this->authService->login($email, $password)) {
            return redirect('/dashboard');
        }

        return view('Auth::login', [
            'title' => 'Sign In — Spinx App',
            'error' => 'Invalid email or password. Please try again.',
            'email' => $email,
        ], 401);
    }

    /**
     * Display the registration view.
     * GET /register
     */
    public function showRegister(?SymfonyRequest $request = null): Response
    {
        return view('Auth::register', [
            'title' => 'Create Account — Spinx App',
            'error' => null,
            'name'  => '',
            'email' => '',
        ]);
    }

    /**
     * Handle account registration.
     * POST /register
     */
    public function register(?SymfonyRequest $request = null): Response
    {
        $name = trim((string) Request::input('name'));
        $email = strtolower(trim((string) Request::input('email')));
        $password = (string) Request::input('password');
        $passwordConfirm = (string) Request::input('password_confirmation');

        if ($name === '' || $email === '' || $password === '') {
            return $this->renderRegisterError('All fields are required.', $name, $email, 422);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->renderRegisterError('Please enter a valid email address.', $name, $email, 422);
        }

        if (strlen($password) < 6) {
            return $this->renderRegisterError('Password must be at least 6 characters.', $name, $email, 422);
        }

        if ($password !== $passwordConfirm) {
            return $this->renderRegisterError('Passwords do not match.', $name, $email, 422);
        }

        try {
            $this->authService->register($name, $email, $password);
            return redirect('/dashboard');
        } catch (\InvalidArgumentException $e) {
            return $this->renderRegisterError($e->getMessage(), $name, $email, 422);
        }
    }

    /**
     * Display the protected user dashboard.
     * GET /dashboard
     */
    public function dashboard(?SymfonyRequest $request = null): Response
    {
        $user = $this->authService->currentUser();

        return view('Auth::dashboard', [
            'title'        => 'Dashboard — Spinx App',
            'user'         => $user,
            'phpVersion'   => PHP_VERSION,
            'spinxVersion' => \Spinx\Kernel\Kernel::VERSION,
        ]);
    }

    /**
     * Handle user logout.
     * POST /logout
     */
    public function logout(?SymfonyRequest $request = null): Response
    {
        $this->authService->logout();

        return redirect('/login');
    }

    private function renderRegisterError(string $message, string $name, string $email, int $status): Response
    {
        return view('Auth::register', [
            'title' => 'Create Account — Spinx App',
            'error' => $message,
            'name'  => $name,
            'email' => $email,
        ], $status);
    }
}
