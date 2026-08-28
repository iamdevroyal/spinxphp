<?php

declare(strict_types=1);

namespace Spinx\Auth\Token;

/**
 * PersonalAccessTokenInterface — Contract for Personal Access Token models / entities.
 */
interface PersonalAccessTokenInterface
{
    /**
     * Determine if the token has a given ability or scope.
     */
    public function can(string $ability): bool;

    /**
     * Determine if the token has expired.
     */
    public function isExpired(): bool;
}
