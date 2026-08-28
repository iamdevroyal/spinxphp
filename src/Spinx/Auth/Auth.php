<?php

declare(strict_types=1);

namespace Spinx\Auth;

use Spinx\Session\SessionInterface;

/**
 * Authentication façade — static resolver, same pattern as Model and DB.
 *
 * Wired at Kernel::boot() with the configured UserProvider and Session.
 * Controllers do not depend on this class directly through constructor
 * injection — they call Auth::check() / Auth::user() statically because
 * auth state is global-to-the-request, same as the session it reads from.
 *
 * Usage:
 *
 *   if (Auth::attempt(['email' => $email, 'password' => $password])) {
 *       return redirect('/dashboard');
 *   }
 *
 *   if (Auth::check()) {
 *       $user = Auth::user(); // returns the authenticated user object
 *   }
 *
 *   Auth::logout();
 */
final class Auth
{
    private const SESSION_KEY = '_spinx_auth_user_id';

    private static ?UserProviderInterface $provider = null;
    private static ?SessionInterface      $session  = null;

    /** API token auth: user resolved by middleware (PAT or JWT), set per-request. */
    private static ?object                $apiUser        = null;

    /** JWT claims array, set by AuthenticateApi middleware for tokenCan() access. */
    private static array                  $tokenClaims    = [];

    /** Called once at Kernel::boot() — not called per-request. */
    public static function boot(UserProviderInterface $provider, SessionInterface $session): void
    {
        self::$provider = $provider;
        self::$session  = $session;
    }

    /**
     * Attempt to authenticate with the given credentials.
     *
     * @param array<string, mixed> $credentials Must include a 'password' key
     *                                          (or whatever field is configured in config/auth.php).
     */
    public static function attempt(array $credentials): bool
    {
        $provider = self::requireProvider();
        $session  = self::requireSession();

        // Extract the password before finding the user — never pass it to the DB.
        $passwordField    = \Spinx\Support\Config::get('auth.password_field', 'password');
        $plainPassword    = $credentials[$passwordField] ?? '';
        $searchCredentials = array_filter(
            $credentials,
            static fn ($k) => $k !== $passwordField,
            ARRAY_FILTER_USE_KEY
        );

        $user = $provider->findByCredentials($searchCredentials);

        if ($user === null || !$provider->validateCredentials($user, (string) $plainPassword)) {
            return false;
        }

        // Regenerate the session ID to prevent session-fixation attacks.
        $session->regenerate();
        $session->set(self::SESSION_KEY, $user->{self::primaryKey()} ?? null);

        return true;
    }

    /**
     * Log in a user object directly — for OAuth flows, token exchanges, or
     * tests where you have the user object but not the raw password.
     */
    public static function login(object $user): void
    {
        $session = self::requireSession();
        $session->regenerate();
        $session->set(self::SESSION_KEY, $user->{self::primaryKey()} ?? null);
    }

    /** Clear the session's user ID and regenerate the session. */
    public static function logout(): void
    {
        $session = self::requireSession();
        $session->forget(self::SESSION_KEY);
        $session->regenerate();
    }

    /** Returns true if a user ID is stored in the current session. */
    public static function check(): bool
    {
        return self::requireSession()->has(self::SESSION_KEY);
    }

    /** Returns true if no user ID is in the current session (inverse of check()). */
    public static function guest(): bool
    {
        return !self::check();
    }

    /**
     * @deprecated Replaced by the unified user() above that checks both API token and session.
     * Kept for internal call sites — do not call directly; use Auth::user() instead.
     */
    private static function resolveSessionUser(): ?object
    {
        if (!self::check()) {
            return null;
        }

        $id = self::id();

        if ($id === null) {
            return null;
        }

        return self::requireProvider()->findById($id);
    }

    /** Returns the authenticated user's primary key value, or null. */
    public static function id(): int|string|null
    {
        return self::requireSession()->get(self::SESSION_KEY);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // API Token Auth Support (used by AuthenticateApi middleware)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Bind an already-resolved user object to Auth for the current request.
     * Called by AuthenticateApi middleware after PAT or JWT verification.
     */
    public static function setUser(object $user): void
    {
        self::$apiUser = $user;
    }

    /**
     * Get the currently authenticated user — checks API token user first,
     * then falls back to session-backed auth.
     */
    public static function user(): ?object
    {
        // API token user takes precedence (set by AuthenticateApi middleware)
        if (self::$apiUser !== null) {
            return self::$apiUser;
        }

        // Fall back to session-based auth
        if (!self::check()) {
            return null;
        }

        $id = self::id();

        if ($id === null) {
            return null;
        }

        return self::requireProvider()->findById($id);
    }

    /**
     * Store decoded JWT payload claims for the current request.
     * Called by AuthenticateApi middleware after JWT verification.
     *
     * @param array<string,mixed> $claims
     */
    public static function setTokenClaims(array $claims): void
    {
        self::$tokenClaims = $claims;
    }

    /**
     * Get the JWT claims for the current request (empty array if not JWT auth).
     *
     * @return array<string,mixed>
     */
    public static function tokenClaims(): array
    {
        return self::$tokenClaims;
    }

    /**
     * Check if the current request token can perform a given ability.
     * Delegates to the user's HasApiTokens::tokenCan() if available,
     * or checks JWT claims directly.
     *
     * @param string $ability  e.g. 'projects:create', 'chapters:read'
     */
    public static function tokenCan(string $ability): bool
    {
        $user = self::user();

        if ($user !== null && method_exists($user, 'tokenCan')) {
            return $user->tokenCan($ability);
        }

        // Fallback: inspect JWT claims directly
        $claims    = self::$tokenClaims;
        $abilities = (array) ($claims['abilities'] ?? $claims['scopes'] ?? ['*']);

        if (in_array('*', $abilities, true)) {
            return true;
        }

        return in_array($ability, $abilities, true);
    }

    /**
     * Reset all per-request API token state.
     * Called automatically by Kernel in the request finally block alongside Csrf::reset().
     */
    public static function resetApiState(): void
    {
        self::$apiUser     = null;
        self::$tokenClaims = [];
    }

    // ---------------------------------------------------------------
    // Internal helpers
    // ---------------------------------------------------------------

    /**
     * Get the active session instance booted into Auth.
     */
    public static function getSession(): ?SessionInterface
    {
        return self::$session;
    }

    private static function primaryKey(): string
    {
        return \Spinx\Support\Config::get('auth.primary_key', 'id');
    }

    private static function requireProvider(): UserProviderInterface
    {
        if (self::$provider === null) {
            throw new \RuntimeException(
                'Auth::boot() has not been called. Auth is wired automatically by Kernel — ' .
                'if you are in a unit test, call Auth::boot() manually first.'
            );
        }

        return self::$provider;
    }

    private static function requireSession(): SessionInterface
    {
        if (self::$session === null) {
            throw new \RuntimeException(
                'Auth::boot() has not been called. Auth is wired automatically by Kernel — ' .
                'if you are in a unit test, call Auth::boot() manually first.'
            );
        }

        return self::$session;
    }
}
