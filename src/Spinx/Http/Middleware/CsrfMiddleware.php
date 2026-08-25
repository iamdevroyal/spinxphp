<?php

declare(strict_types=1);

namespace Spinx\Http\Middleware;

use Spinx\Security\Csrf;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Attach to routes/modules that render forms or accept state-changing
 * requests — not global by default (a pure JSON API authenticated via
 * bearer tokens has no cookies to protect and doesn't need this; see
 * docs/security.md).
 */
final class CsrfMiddleware implements MiddlewareInterface
{
    private const STATE_CHANGING_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function process(Request $request, \Closure $next): Response
    {
        $token = Csrf::tokenForRequest($request);

        if (in_array($request->getMethod(), self::STATE_CHANGING_METHODS, true)) {
            $submitted = $request->request->get('_token') 
                ?? $request->headers->get('X-CSRF-TOKEN') 
                ?? $request->headers->get('X-XSRF-TOKEN');

            if (!is_string($submitted) || !Csrf::verify($submitted, $request)) {
                return new Response('CSRF token mismatch.', 419);
            }
        }

        $response = $next($request);

        // Re-set on every response so the browser always has a valid,
        // fresh-expiry token for the next request — httpOnly is
        // deliberately false: the whole point of this cookie is that
        // client-side code (or the @csrf-rendered form field) can read
        // it to submit it back. Using the fluent with*() builder rather
        // than Cookie::create()'s many positional/named arguments, which
        // this project can't fully verify the exact parameter order for
        // without a real DBAL-style install to check against — the
        // with*() method names are individually confirmed directly from
        // Symfony's own documentation.
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
