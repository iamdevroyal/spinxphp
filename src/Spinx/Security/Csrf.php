<?php

declare(strict_types=1);

namespace Spinx\Security;

use Spinx\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Session-backed CSRF protection with cookie synchronization.
 *
 * The CSRF token is securely stored and managed in the user's active session.
 * For client-side compatibility (e.g. JavaScript fetch/axios requests),
 * the token is also synchronized to an XSRF-TOKEN cookie and verified against
 * the session token on state-changing requests (POST, PUT, PATCH, DELETE).
 */
final class Csrf
{
    public const COOKIE_NAME = 'XSRF-TOKEN';
    public const SESSION_KEY = '_token';

    private static ?string $currentToken = null;

    /**
     * Retrieves or generates the CSRF token for the given session.
     */
    public static function tokenForSession(SessionInterface $session): string
    {
        $token = $session->get(self::SESSION_KEY);

        if (!is_string($token) || $token === '') {
            $token = self::generateToken();
            $session->set(self::SESSION_KEY, $token);
        }

        return self::$currentToken = $token;
    }

    /**
     * Regenerates the CSRF token stored in the session (e.g. on login/logout).
     */
    public static function regenerateToken(SessionInterface $session): string
    {
        $token = self::generateToken();
        $session->set(self::SESSION_KEY, $token);

        return self::$currentToken = $token;
    }

    /**
     * Called by CsrfMiddleware to initialize token from session or cookie.
     */
    public static function tokenForRequest(Request $request, ?SessionInterface $session = null): string
    {
        if ($session !== null) {
            return self::tokenForSession($session);
        }

        $cookieToken = $request->cookies->get(self::COOKIE_NAME);

        return self::$currentToken = (is_string($cookieToken) && $cookieToken !== '') 
            ? $cookieToken 
            : self::generateToken();
    }

    /**
     * The token for the current request — used by @csrf directive.
     */
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

    /**
     * Verifies the submitted token against the session token or active cookie.
     */
    public static function verify(
        string $submitted, 
        ?Request $request = null, 
        ?SessionInterface $session = null
    ): bool {
        if ($submitted === '') {
            return false;
        }

        // 1. Verify against session token if session is available
        if ($session !== null) {
            $sessionToken = $session->get(self::SESSION_KEY);
            if (is_string($sessionToken) && $sessionToken !== '' && hash_equals($sessionToken, $submitted)) {
                return true;
            }
        }

        // 2. Verify against cookie token if available
        $expected = $request?->cookies->get(self::COOKIE_NAME);
        if (is_string($expected) && $expected !== '' && hash_equals($expected, $submitted)) {
            return true;
        }

        // 3. Verify against static request-cycle token
        return self::$currentToken !== null && hash_equals(self::$currentToken, $submitted);
    }
}
