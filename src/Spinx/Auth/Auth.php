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
     * Returns the authenticated user object, or null if not authenticated.
     * Resolves the user from the provider on every call — no per-request
     * caching here because the session is already the cache.
     */
    public static function user(): ?object
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

    // ---------------------------------------------------------------
    // Internal helpers
    // ---------------------------------------------------------------

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
