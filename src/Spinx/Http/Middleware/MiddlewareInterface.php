<?php

declare(strict_types=1);

namespace Spinx\Http\Middleware;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Deliberately NOT literal PSR-15 (MiddlewareInterface/RequestHandlerInterface
 * from psr/http-server-middleware), even though "PSR-15 compatible" was the
 * original build spec wording. PSR-15's interfaces operate on PSR-7
 * ServerRequestInterface/ResponseInterface — every other part of this
 * framework deliberately works with Symfony's Request/Response only, with
 * PSR-7 bridging happening exactly once, at the runtime adapter boundary
 * (see Spinx\Runtime\ServerAdapter's docblock). Adopting literal PSR-15
 * would mean bridging Symfony<->PSR-7 on every middleware call in a
 * chain, for every request, which contradicts that design and buys
 * nothing since nothing in this project's scope needs interop with the
 * broader third-party PSR-15 middleware ecosystem. This interface keeps
 * the familiar "process, call $next" shape without the type mismatch.
 */
interface MiddlewareInterface
{
    /**
     * @param \Closure(Request): Response $next Call with the (possibly
     *        modified) request to continue the pipeline; its return
     *        value is the Response to (possibly modify and) return.
     */
    public function process(Request $request, \Closure $next): Response;
}
