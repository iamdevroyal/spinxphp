<?php

declare(strict_types=1);

namespace Spinx\Auth\Token;

/**
 * Token — Static façade over TokenManager.
 *
 * Usage:
 *
 *   // Create a token for a user
 *   $newToken = Token::createToken($user, 'iPhone App', ['projects:read', 'chapters:write']);
 *
 *   // Find and validate a raw bearer string from an Authorization header
 *   $token = Token::findToken($rawBearerString);
 *   if ($token === null) { ... invalid ... }
 *
 *   // Revoke all tokens for a user
 *   Token::revokeAll($user);
 *
 *   // Revoke by device name
 *   Token::revokeByName($user, 'iPhone App');
 */
final class Token
{
    private static ?TokenManager $instance = null;

    // ─────────────────────────────────────────────────────────────────────────
    // Bootstrap
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Replace the underlying manager (useful for testing / mocking).
     */
    public static function setManager(TokenManager $manager): void
    {
        self::$instance = $manager;
    }

    private static function manager(): TokenManager
    {
        return self::$instance ??= new TokenManager();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Public API
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Create a new Personal Access Token for the given user/entity.
     *
     * @param object   $user       Any entity with ->id or getId()
     * @param string   $name       Device/app name, e.g. "MacBook Pro Dev"
     * @param array<string> $abilities Scopes: ['*'] for omniscient, or ['read', 'write']
     * @param \DateTimeInterface|null $expiresAt Optional hard expiration date
     * @return NewAccessToken      Hold onto ->plainTextToken — shown only once
     */
    public static function createToken(
        object $user,
        string $name,
        array $abilities = ['*'],
        ?\DateTimeInterface $expiresAt = null,
    ): NewAccessToken {
        return self::manager()->createToken($user, $name, $abilities, $expiresAt);
    }

    /**
     * Find and validate a raw bearer token string from an HTTP header.
     *
     * @param  string  $rawToken  The raw string from "Authorization: Bearer {rawToken}"
     * @return PersonalAccessToken|null  The valid, non-expired token record, or null
     */
    public static function findToken(string $rawToken): ?PersonalAccessToken
    {
        return self::manager()->findToken($rawToken);
    }

    /**
     * Revoke all Personal Access Tokens for the given user entity.
     *
     * @return int  Number of tokens deleted
     */
    public static function revokeAll(object $user): int
    {
        return self::manager()->revokeAllTokens($user);
    }

    /**
     * Revoke tokens by device/app name for a given user entity.
     *
     * @return int  Number of tokens deleted
     */
    public static function revokeByName(object $user, string $name): int
    {
        return self::manager()->revokeTokensByName($user, $name);
    }
}
