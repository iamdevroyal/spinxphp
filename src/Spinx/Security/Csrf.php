<?php

declare(strict_types=1);

namespace Spinx\Security;

use Spinx\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Session-backed CSRF protection with cookie synchronization and persistent worker isolation.
 */
final class Csrf
{
    public const COOKIE_NAME = 'XSRF-TOKEN';
    public const SESSION_KEY = '_token';

    private static ?string $currentToken = null;
    /** @var array<int, string> */
    private static array $coroutineTokens = [];

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

        self::setCurrentToken($token);

        return $token;
    }

    /**
     * Regenerates the CSRF token stored in the session (e.g. on login/logout).
     */
    public static function regenerateToken(SessionInterface $session): string
    {
        $token = self::generateToken();
        $session->set(self::SESSION_KEY, $token);
        self::setCurrentToken($token);

        return $token;
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
        $token = (is_string($cookieToken) && $cookieToken !== '') 
            ? $cookieToken 
            : self::generateToken();

        self::setCurrentToken($token);

        return $token;
    }

    /**
     * The token for the current request — used by @csrf directive.
     */
    public static function current(): string
    {
        $existing = self::getCurrentToken();
        if ($existing !== null) {
            return $existing;
        }

        $generated = self::generateToken();
        self::setCurrentToken($generated);

        return $generated;
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
     * Reset CSRF token for the current request/coroutine.
     * Called by Kernel::handle() in the finally block.
     */
    public static function reset(): void
    {
        if (function_exists('swoole_coroutine_get_cid') && swoole_coroutine_get_cid() > 0) {
            unset(self::$coroutineTokens[(int) swoole_coroutine_get_cid()]);
            return;
        }

        self::$currentToken = null;
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

        // 1. If session is available, verify against session token
        if ($session !== null) {
            $sessionToken = $session->get(self::SESSION_KEY);
            if (is_string($sessionToken) && $sessionToken !== '') {
                return hash_equals($sessionToken, $submitted);
            }
        }

        // 2. Verify against cookie token if available (for stateless API double-submit)
        $expected = $request?->cookies->get(self::COOKIE_NAME);
        if (is_string($expected) && $expected !== '' && hash_equals($expected, $submitted)) {
            return true;
        }

        // 3. Verify against current request-cycle token
        $curr = self::getCurrentToken();
        return $curr !== null && hash_equals($curr, $submitted);
    }

    private static function setCurrentToken(?string $token): void
    {
        if (function_exists('swoole_coroutine_get_cid') && swoole_coroutine_get_cid() > 0) {
            $cid = (int) swoole_coroutine_get_cid();
            if ($token === null) {
                unset(self::$coroutineTokens[$cid]);
            } else {
                self::$coroutineTokens[$cid] = $token;
            }
            return;
        }

        self::$currentToken = $token;
    }

    private static function getCurrentToken(): ?string
    {
        if (function_exists('swoole_coroutine_get_cid') && swoole_coroutine_get_cid() > 0) {
            $cid = (int) swoole_coroutine_get_cid();
            return self::$coroutineTokens[$cid] ?? null;
        }

        return self::$currentToken;
    }
}
