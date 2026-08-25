<?php

declare(strict_types=1);

namespace Spinx\Auth;

/**
 * Password hashing helpers — thin wrappers over PHP's native bcrypt functions.
 *
 * Always uses PASSWORD_BCRYPT (not PASSWORD_DEFAULT) so the algorithm is
 * stable across PHP versions and the hash length is predictable for database
 * column sizing. PASSWORD_DEFAULT may change in future PHP versions; a hash
 * column sized for bcrypt (60 chars) would silently truncate an Argon2 hash.
 *
 * Usage:
 *   $hash  = Hash::make($request->request->get('password'));
 *   $valid = Hash::check($plain, $user->password);
 */
final class Hash
{
    /**
     * Hash a plaintext password.
     *
     * @param int $cost Bcrypt cost factor (4–31). Default 12 matches Laravel's
     *                  default and is appropriate for most production hardware.
     *                  Increase on faster hardware; keep >= 10 for security.
     */
    public static function make(string $password, int $cost = 12): string
    {
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => $cost]);

        if ($hash === false) {
            throw new \RuntimeException('password_hash() failed — this should never happen with PASSWORD_BCRYPT.');
        }

        return $hash;
    }

    /**
     * Verify a plaintext password against a stored hash.
     * Uses constant-time comparison internally (password_verify guarantees this).
     */
    public static function check(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Returns true if the hash was created with a lower cost than $cost,
     * meaning it should be re-hashed after a successful login.
     * Useful for rolling cost factor upgrades without forcing password resets.
     */
    public static function needsRehash(string $hash, int $cost = 12): bool
    {
        return password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => $cost]);
    }
}
