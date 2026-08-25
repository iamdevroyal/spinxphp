<?php

declare(strict_types=1);

namespace Spinx\Http\Middleware;

use Spinx\Support\Config;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Config-driven CORS handling — see config/cors.php. Not wired
 * globally by default (attach it per-route or per-module, same as any
 * other middleware — see docs/security.md for why global-by-default is
 * the wrong call for a framework, not just this one: an app with no API
 * consumers has no reason to pay the preflight-handling cost on every
 * request).
 *
 * Reflects the request's actual Origin back rather than literally
 * echoing "*" whenever credentials are allowed — the CORS spec forbids
 * combining a wildcard origin with Access-Control-Allow-Credentials:
 * true, and browsers enforce this by rejecting the response outright.
 */
final class CorsMiddleware implements MiddlewareInterface
{
    public function process(Request $request, \Closure $next): Response
    {
        $allowedOrigins = Config::instance()->get('cors.allowed_origins', ['*']);
        $allowCredentials = (bool) Config::instance()->get('cors.allow_credentials', false);
        $requestOrigin = $request->headers->get('Origin');

        // Preflight — short-circuit before the controller runs at all.
        if ($request->getMethod() === 'OPTIONS') {
            $response = new Response('', 204);
        } else {
            $response = $next($request);
        }

        $allowOriginHeader = $this->resolveAllowOrigin($allowedOrigins, $requestOrigin, $allowCredentials);

        if ($allowOriginHeader !== null) {
            $response->headers->set('Access-Control-Allow-Origin', $allowOriginHeader);
            $response->headers->set('Vary', 'Origin');
        }

        if ($allowCredentials) {
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        }

        $response->headers->set('Access-Control-Allow-Methods', implode(', ', Config::instance()->get('cors.allowed_methods', [])));
        $response->headers->set('Access-Control-Allow-Headers', implode(', ', Config::instance()->get('cors.allowed_headers', [])));
        $response->headers->set('Access-Control-Max-Age', (string) Config::instance()->get('cors.max_age', 0));

        return $response;
    }

    /** @param string[] $allowedOrigins */
    private function resolveAllowOrigin(array $allowedOrigins, ?string $requestOrigin, bool $allowCredentials): ?string
    {
        if (in_array('*', $allowedOrigins, true)) {
            // Credentialed requests can never legally use "*" — reflect
            // the actual origin instead so the response is still valid,
            // rather than silently sending a header the browser will
            // just reject anyway.
            return $allowCredentials ? ($requestOrigin ?? '*') : '*';
        }

        if ($requestOrigin !== null && in_array($requestOrigin, $allowedOrigins, true)) {
            return $requestOrigin;
        }

        return null;
    }
}
