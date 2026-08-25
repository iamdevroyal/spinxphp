<?php

declare(strict_types=1);

namespace Spinx\Http\Middleware;

use Spinx\Security\Csrf;
use Spinx\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Session-backed CSRF protection middleware with cookie synchronization.
 *
 * Verifies that incoming state-changing requests (POST, PUT, PATCH, DELETE)
 * provide a valid CSRF token matching the user's active session.
 */
final class CsrfMiddleware implements MiddlewareInterface
{
    private const STATE_CHANGING_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function __construct(
        private readonly ?SessionInterface $session = null,
    ) {
    }

    public function process(Request $request, \Closure $next): Response
    {
        $token = Csrf::tokenForRequest($request, $this->session);

        if (in_array($request->getMethod(), self::STATE_CHANGING_METHODS, true)) {
            $submitted = $request->request->get('_token') 
                ?? $request->headers->get('X-CSRF-TOKEN') 
                ?? $request->headers->get('X-XSRF-TOKEN');

            if (!is_string($submitted) || !Csrf::verify($submitted, $request, $this->session)) {
                return new Response('CSRF token mismatch.', 419);
            }
        }

        $response = $next($request);

        // Synchronize XSRF-TOKEN cookie on outgoing response so frontend SPA/axios can read it
        $cookie = Cookie::create(Csrf::COOKIE_NAME)
            ->withValue($token)
            ->withExpires(0)
            ->withPath('/')
            ->withSecure(false)
            ->withHttpOnly(false)
            ->withSameSite('Lax');

        $response->headers->setCookie($cookie);

        return $response;
    }
}
