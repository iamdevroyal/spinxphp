<?php

declare(strict_types=1);

namespace Spinx\Security;

use Symfony\Component\HttpFoundation\Request;

/**
 * Double-submit-cookie CSRF protection — deliberately not
 * session-backed, since no session subsystem exists in this framework
 * yet. The pattern: a random token is set as a cookie AND echoed into
 * every form via the @csrf directive; a state-changing request is only
 * accepted if the submitted token matches the cookie, which an
 * attacker's cross-site form can't read or set (browsers enforce
 * same-origin on cookie access) even though they CAN trigger a
 * cross-site POST. This is a real, legitimate, widely-used CSRF defense
 * (Angular, axios, and others use exactly this pattern) — not a
 * placeholder for "real" session-based CSRF later.
 *
 * The current-token state is intentionally static — framework-level
 * per-request state, same category as Model's connection manager or
 * RateLimitMiddleware's store (see their docblocks), not the kind of
 * app/Modules business-logic state the PHPStan rule from build spec §4
 * targets. CsrfMiddleware sets it at the start of every request, so it
 * never leaks between requests despite being static.
 */
final class Csrf
{
    public const COOKIE_NAME = 'XSRF-TOKEN';

    private static ?string $currentToken = null;

    /** Called once per request by CsrfMiddleware — reads the existing cookie token, or generates a fresh one if none exists yet. */
    public static function tokenForRequest(Request $request): string
    {
        $cookieToken = $request->cookies->get(self::COOKIE_NAME);

        return self::$currentToken = (is_string($cookieToken) && $cookieToken !== '') ? $cookieToken : self::generateToken();
    }

    /** The token for the CURRENT request — read by the @csrf directive via TemplateRenderer::csrfField(). Generates on-demand if not already initialized. */
    public static function current(): string
    {
        return self::$currentToken ??= self::generateToken();
    }

    public static function token(): string
    {
        return self::current();
    }

    public static function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public static function verify(string $submitted, ?Request $request = null): bool
    {
        if ($submitted === '') {
            return false;
        }

        $expected = $request?->cookies->get(self::COOKIE_NAME);
        if (is_string($expected) && $expected !== '' && hash_equals($expected, $submitted)) {
            return true;
        }

        return self::$currentToken !== null && hash_equals(self::$currentToken, $submitted);
    }
}
