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
 * Ensures the incoming request is from a guest (unauthenticated user).
 * Redirects authenticated users away from login/registration pages.
 *
 * Usage in module.php:
 *   Route::get(['login', '/login'])
 *       ->middleware(['guest'])
 *       ->controller('login_controller');
 */
final class GuestMiddleware implements MiddlewareInterface
{
    public function process(Request $request, \Closure $next): Response
    {
        if (Auth::check()) {
            $wantsJson = $request->isXmlHttpRequest() 
                || str_contains($request->headers->get('Accept', ''), 'application/json');

            if ($wantsJson) {
                return new JsonResponse(['message' => 'Already authenticated.'], 400);
            }

            $home = (string) \Spinx\Support\Config::get('auth.home', '/');
            return new RedirectResponse($home);
        }

        return $next($request);
    }
}
