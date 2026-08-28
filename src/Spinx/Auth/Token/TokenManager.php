<?php

declare(strict_types=1);

namespace Spinx\Auth\Token;

use Spinx\Support\Config;

/**
 * TokenManager — Core service for generating, hashing, validating, and
 * revoking Personal Access Tokens in the `personal_access_tokens` table.
 *
 * Token format: spinx_pat_{id}|{plaintext_64_hex_characters}
 *
 * Only the SHA-256 hash of the plaintext segment is ever persisted.
 * The ID prefix enables fast record lookup (2-part parse) before hash comparison.
 */
final class TokenManager
{
    private string $prefix;

    public function __construct()
    {
        $this->prefix = Config::get('auth.api.token_prefix', 'spinx_pat_');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Token Creation
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Create a new Personal Access Token for a user-like entity.
     *
     * @param object   $user         Any entity with getId() or ->id
     * @param string   $name         A human-readable name for the token (e.g. "iOS App")
     * @param array<string> $abilities Token scopes (e.g. ['projects:create', '*'])
     * @param \DateTimeInterface|null $expiresAt  Optional expiry date
     * @return NewAccessToken  Contains plainTextToken (show once) + persisted accessToken record
     */
    public function createToken(
        object $user,
        string $name,
        array $abilities = ['*'],
        ?\DateTimeInterface $expiresAt = null,
    ): NewAccessToken {
        // 1. Generate cryptographically secure 32-byte (64 hex char) random token
        $plaintext = bin2hex(random_bytes(32));

        // 2. Resolve the user's primary key
        $userId = $this->resolveId($user);

        // 3. Persist the SHA-256 hash
        $record = PersonalAccessToken::createRecord(
            tokenableType: get_class($user),
            tokenableId: $userId,
            name: $name,
            hashedToken: hash('sha256', $plaintext),
            abilities: $abilities,
            expiresAt: $expiresAt,
        );

        // 4. Build the full plaintext bearer string: "spinx_pat_{id}|{plaintext}"
        $fullPlaintext = $this->prefix . $record->id . '|' . $plaintext;

        return new NewAccessToken($record, $fullPlaintext);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Token Validation & Lookup
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Attempt to find and validate a raw bearer token string from an HTTP request.
     *
     * Returns the PersonalAccessToken record on success, null on failure.
     */
    public function findToken(string $rawToken): ?PersonalAccessToken
    {
        // Strip the prefix if present
        if (str_starts_with($rawToken, $this->prefix)) {
            $rawToken = substr($rawToken, strlen($this->prefix));
        }

        // Expect format: {id}|{plaintext}
        if (!str_contains($rawToken, '|')) {
            return null;
        }

        [$id, $plaintext] = explode('|', $rawToken, 2);

        if (!is_numeric($id) || strlen($plaintext) === 0) {
            return null;
        }

        // Hash the plaintext for comparison
        $hash   = hash('sha256', $plaintext);
        $record = PersonalAccessToken::findByHash($hash);

        if ($record === null) {
            return null;
        }

        // Double-check the ID segment to prevent timing attacks on hash collision
        if ((int) $record->id !== (int) $id) {
            return null;
        }

        // Check expiry
        if ($record->isExpired()) {
            return null;
        }

        return $record;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Revocation
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Revoke all tokens for a given user entity.
     */
    public function revokeAllTokens(object $user): int
    {
        return (int) PersonalAccessToken::where('tokenable_type', get_class($user))
            ->where('tokenable_id', $this->resolveId($user))
            ->delete();
    }

    /**
     * Revoke tokens by name for a given user entity.
     */
    public function revokeTokensByName(object $user, string $name): int
    {
        return (int) PersonalAccessToken::where('tokenable_type', get_class($user))
            ->where('tokenable_id', $this->resolveId($user))
            ->where('name', $name)
            ->delete();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function resolveId(object $entity): int|string
    {
        if (method_exists($entity, 'getId')) {
            return $entity->getId();
        }

        if (isset($entity->id)) {
            return $entity->id;
        }

        throw new \LogicException(
            'Entity class ' . get_class($entity) . ' must expose ->id or getId() for token association.'
        );
    }
}
