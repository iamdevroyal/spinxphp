<?php

declare(strict_types=1);

namespace App\Modules\Health\Infrastructure\Http\Middleware;

use Spinx\Http\Middleware\MiddlewareInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reference middleware proving the pipeline from
 * Spinx\Http\Middleware\Pipeline actually executes end to end — wired to
 * the /health route in this module's module.php. Adds two response
 * headers after the controller runs, demonstrating the "work after
 * $next()" half of the contract (the "work before" half — auth checks,
 * short-circuiting with an early Response — is documented in the
 * generated stub for new middleware).
 */
final class SecurityHeadersMiddleware implements MiddlewareInterface
{
    public function process(Request $request, \Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Spinx-Middleware', 'SecurityHeadersMiddleware ran');

        return $response;
    }
}
