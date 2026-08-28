<?php

declare(strict_types=1);

namespace Spinx\Auth\Token;

use Spinx\Database\Model;

/**
 * PersonalAccessToken — Active Record model for the `personal_access_tokens` table.
 *
 * Stores a sha256-hashed bearer token along with its associated user entity type/id,
 * optional ability scope array, expiration timestamp, and last-used tracking.
 *
 * The plaintext token string is NEVER stored here. It is held only in
 * NewAccessToken::$plainTextToken for the lifetime of the creation response.
 *
 * Table: personal_access_tokens
 * Columns: id, tokenable_type, tokenable_id, name, token (sha256), abilities (json),
 *          last_used_at, expires_at, created_at, updated_at
 */
class PersonalAccessToken extends Model implements PersonalAccessTokenInterface
{
    protected static string $table = 'personal_access_tokens';

    protected array $fillable = [
        'tokenable_type',
        'tokenable_id',
        'name',
        'token',
        'abilities',
        'last_used_at',
        'expires_at',
    ];

    protected array $casts = [
        'abilities'    => 'array',
        'last_used_at' => 'datetime',
        'expires_at'   => 'datetime',
    ];

    // ─────────────────────────────────────────────────────────────────────────
    // Factory
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Create a new token record.
     *
     * @param string   $tokenableType Fully-qualified entity class name
     * @param int|string $tokenableId Entity primary key value
     * @param string   $name         Human-readable name (e.g. "MacBook Dev")
     * @param string   $hashedToken  sha256 hash of the plaintext bearer token
     * @param array<string> $abilities  Scopes, e.g. ['*'] or ['projects:read']
     * @param \DateTimeInterface|null $expiresAt Optional expiration
     */
    public static function createRecord(
        string $tokenableType,
        int|string $tokenableId,
        string $name,
        string $hashedToken,
        array $abilities = ['*'],
        ?\DateTimeInterface $expiresAt = null,
    ): self {
        $record = new self();
        $record->tokenable_type = $tokenableType;
        $record->tokenable_id   = $tokenableId;
        $record->name           = $name;
        $record->token          = $hashedToken;
        $record->abilities      = $abilities;
        $record->expires_at     = $expiresAt?->format('Y-m-d H:i:s');
        $record->save();

        return $record;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Lookup
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Find a token record by its SHA-256 hash.
     */
    public static function findByHash(string $hash): ?self
    {
        $row = static::where('token', $hash)->first();
        return $row instanceof self ? $row : null;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // State Queries
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Whether this token grants the given ability.
     * Tokens with ['*'] are omniscient.
     */
    public function can(string $ability): bool
    {
        $abilities = (array) ($this->abilities ?? []);

        if (in_array('*', $abilities, true)) {
            return true;
        }

        return in_array($ability, $abilities, true);
    }

    /**
     * Returns true if the token has expired.
     */
    public function isExpired(): bool
    {
        if ($this->expires_at === null) {
            return false;
        }

        return strtotime((string) $this->expires_at) < time();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Mutations
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Mark the token as recently used.
     */
    public function touchLastUsed(): void
    {
        static::where('id', $this->id)->update([
            'last_used_at' => date('Y-m-d H:i:s'),
        ]);
        $this->last_used_at = date('Y-m-d H:i:s');
    }

    /**
     * Permanently delete this token (revocation).
     */
    public function revoke(): bool
    {
        return (bool) static::where('id', $this->id)->delete();
    }
}
