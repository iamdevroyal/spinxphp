<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Http\Controllers;

use App\Modules\Auth\Application\Services\AuthService;
use Spinx\Http\Request;
use Spinx\Http\Response;
use Spinx\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Unified Auth Controller.
 *
 * In strict DDD architecture, this controller is ONLY responsible for:
 *   1. HTTP Request validation using Request::validate() / Validator facade
 *   2. Delegating business logic to AuthService
 *   3. Returning appropriate View templates or HTTP Responses via facades.
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
    public function showLogin(): SymfonyResponse
    {
        return view('Auth::login', [
            'title'  => 'Sign In — Spinx App',
            'errors' => [],
            'email'  => '',
        ]);
    }

    /**
     * Handle login authentication.
     * POST /login
     */
    public function login(): SymfonyResponse
    {
        try {
            $data = Request::validate([
                'email'    => 'required|email|max:255',
                'password' => 'required|string|min:1',
            ]);
        } catch (ValidationException $e) {
            return view('Auth::login', [
                'title'  => 'Sign In — Spinx App',
                'errors' => $e->errors(),
                'email'  => Request::input('email', ''),
            ], 422);
        }

        if ($this->authService->login($data['email'], $data['password'])) {
            return redirect('/dashboard');
        }

        return view('Auth::login', [
            'title'  => 'Sign In — Spinx App',
            'errors' => ['email' => ['Invalid email or password. Please try again.']],
            'email'  => $data['email'],
        ], 401);
    }

    /**
     * Display the registration view.
     * GET /register
     */
    public function showRegister(): SymfonyResponse
    {
        return view('Auth::register', [
            'title'  => 'Create Account — Spinx App',
            'errors' => [],
            'name'   => '',
            'email'  => '',
        ]);
    }

    /**
     * Handle account registration.
     * POST /register
     */
    public function register(): SymfonyResponse
    {
        try {
            $data = Request::validate([
                'name'                  => 'required|string|min:2|max:100',
                'email'                 => 'required|email|max:255',
                'password'              => 'required|string|min:6|confirmed',
                'password_confirmation' => 'required|string|min:6',
            ]);
        } catch (ValidationException $e) {
            return view('Auth::register', [
                'title'  => 'Create Account — Spinx App',
                'errors' => $e->errors(),
                'name'   => Request::input('name', ''),
                'email'  => Request::input('email', ''),
            ], 422);
        }

        try {
            $this->authService->register($data['name'], $data['email'], $data['password']);
            return redirect('/dashboard');
        } catch (\InvalidArgumentException $e) {
            return view('Auth::register', [
                'title'  => 'Create Account — Spinx App',
                'errors' => ['email' => [$e->getMessage()]],
                'name'   => $data['name'],
                'email'  => $data['email'],
            ], 422);
        }
    }

    /**
     * Display the protected user dashboard.
     * GET /dashboard
     */
    public function dashboard(): SymfonyResponse
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
    public function logout(): SymfonyResponse
    {
        $this->authService->logout();

        return redirect('/login');
    }
}
