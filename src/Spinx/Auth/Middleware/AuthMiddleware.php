<?php

declare(strict_types=1);

namespace Spinx\Auth\Middleware;

use Spinx\Auth\Auth;
use Spinx\Http\Middleware\MiddlewareInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures the incoming request is from an authenticated user.
 *
 * Usage in module.php:
 *   Route::get(['dashboard', '/dashboard'])
 *       ->middleware(['auth'])
 *       ->controller('dashboard_controller');
 */
final class AuthMiddleware implements MiddlewareInterface
{
    public function process(Request $request, \Closure $next): Response
    {
        if (!Auth::check()) {
            $unauthenticatedMode = \Spinx\Support\Config::get('auth.unauthenticated', 'redirect');
            $wantsJson = $request->isXmlHttpRequest() 
                || str_contains($request->headers->get('Accept', ''), 'application/json')
                || $unauthenticatedMode === 'json';

            if ($wantsJson) {
                return new JsonResponse(['message' => 'Unauthenticated.'], 401);
            }

            $redirectTo = (string) \Spinx\Support\Config::get('auth.redirect_to', '/login');
            return new RedirectResponse($redirectTo);
        }

        return $next($request);
    }
}
