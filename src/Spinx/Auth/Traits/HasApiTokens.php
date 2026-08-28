<?php

declare(strict_types=1);

namespace Spinx\Auth\Traits;

use Spinx\Auth\Token\NewAccessToken;
use Spinx\Auth\Token\PersonalAccessToken;
use Spinx\Auth\Token\Token;

/**
 * HasApiTokens — Add Personal Access Token capabilities to any User entity or Model.
 *
 * Usage (in your User Model or Domain Entity):
 *
 *   use Spinx\Auth\Traits\HasApiTokens;
 *
 *   final class User extends Model
 *   {
 *       use HasApiTokens;
 *       // ...
 *   }
 *
 *   // Then in a controller:
 *   $newToken = $user->createToken('iPhone 15 Pro', ['projects:read']);
 *   $tokens   = $user->tokens();
 *   $user->revokeTokens();
 *   $user->revokeTokensByName('iPhone 15 Pro');
 *
 *   // Check ability of currently active request token (set by AuthenticateApi middleware):
 *   if ($user->tokenCan('projects:create')) { ... }
 */
trait HasApiTokens
{
    /**
     * The currently authenticated PAT record for this user (set per-request by AuthenticateApi middleware).
     * Null when using JWT auth or not yet resolved.
     */
    private ?PersonalAccessToken $currentAccessToken = null;

    // ─────────────────────────────────────────────────────────────────────────
    // Token Creation
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Create a new Personal Access Token for this user.
     *
     * @param string $name          Human-readable device/app name (e.g. "MacBook Pro")
     * @param array<string> $abilities  Scopes: ['*'] for full access or ['projects:read']
     * @param \DateTimeInterface|null $expiresAt  Optional expiration date
     * @return NewAccessToken  Contains ->plainTextToken (show only once!) and ->accessToken record
     */
    public function createToken(
        string $name,
        array $abilities = ['*'],
        ?\DateTimeInterface $expiresAt = null,
    ): NewAccessToken {
        return Token::createToken($this, $name, $abilities, $expiresAt);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Token Queries
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Retrieve all tokens for this user from the database.
     *
     * @return array<PersonalAccessToken>
     */
    public function tokens(): array
    {
        return PersonalAccessToken::where('tokenable_type', get_class($this))
            ->where('tokenable_id', $this->resolveEntityId())
            ->get();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Ability Checking
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Check if the currently authenticated token has the given ability/scope.
     *
     * When using JWT auth (where there is no PAT record), this checks the
     * 'abilities' or 'scopes' claim in the JWT payload stored on Auth::tokenClaims().
     *
     * Returns true for ['*'] omniscient tokens.
     *
     * @param string $ability  e.g. 'projects:create', 'chapters:read'
     */
    public function tokenCan(string $ability): bool
    {
        // Check PAT ability
        if ($this->currentAccessToken !== null) {
            return $this->currentAccessToken->can($ability);
        }

        // Check JWT claims (populated by AuthenticateApi middleware)
        $claims = \Spinx\Auth\Auth::tokenClaims();
        if (!empty($claims)) {
            $abilities = (array) ($claims['abilities'] ?? $claims['scopes'] ?? ['*']);
            if (in_array('*', $abilities, true)) {
                return true;
            }
            return in_array($ability, $abilities, true);
        }

        return false;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Token Revocation
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Revoke all Personal Access Tokens for this user.
     */
    public function revokeTokens(): int
    {
        return Token::revokeAll($this);
    }

    /**
     * Revoke tokens by device/app name for this user.
     */
    public function revokeTokensByName(string $name): int
    {
        return Token::revokeByName($this, $name);
    }

    /**
     * Revoke the current request's token (for "sign out from this device").
     */
    public function revokeCurrentToken(): bool
    {
        if ($this->currentAccessToken !== null) {
            return $this->currentAccessToken->revoke();
        }
        return false;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Internal / Framework Use
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Set the currently active PAT token for this request (called by AuthenticateApi middleware).
     */
    public function withAccessToken(PersonalAccessToken $token): static
    {
        $this->currentAccessToken = $token;
        return $this;
    }

    /**
     * Get the currently active PAT token, if any.
     */
    public function currentAccessToken(): ?PersonalAccessToken
    {
        return $this->currentAccessToken;
    }

    private function resolveEntityId(): int|string
    {
        if (method_exists($this, 'getId')) {
            return $this->getId();
        }
        if (isset($this->id)) {
            return $this->id;
        }
        throw new \LogicException(get_class($this) . ' must expose ->id or getId() for HasApiTokens.');
    }
}
