<?php

declare(strict_types=1);

namespace Spinx\Auth;

/**
 * Contract for resolving user records from a backing store.
 *
 * Implement this interface to use a custom user source (e.g. an LDAP
 * directory, a remote API, or a non-Eloquent model). Register your
 * implementation in config/auth.php as 'provider'.
 */
interface UserProviderInterface
{
    /**
     * Find a user by their primary key — called by Auth::user() to
     * reconstruct the user from the session-stored ID.
     */
    public function findById(int|string $id): ?object;

    /**
     * Find a user by login credentials — called by Auth::attempt().
     * $credentials contains the field names and values to search by
     * (e.g. ['email' => 'user@example.com']). Do NOT include the
     * password in the query — use validateCredentials() for that.
     *
     * @param array<string, mixed> $credentials
     */
    public function findByCredentials(array $credentials): ?object;

    /**
     * Returns true if $password matches the stored hash on $user.
     * Typically delegates to password_verify() — see Hash::check().
     */
    public function validateCredentials(object $user, string $password): bool;
}
