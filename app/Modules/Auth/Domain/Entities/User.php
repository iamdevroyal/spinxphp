<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain\Entities;

/**
 * Pure Domain Entity representing a User.
 * Holds no persistence awareness or active-record state.
 */
final class User
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $name,
        public readonly string $email,
        public readonly string $passwordHash,
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null,
    ) {
    }

    public static function create(string $name, string $email, string $passwordHash): self
    {
        return new self(
            id: null,
            name: $name,
            email: strtolower(trim($email)),
            passwordHash: $passwordHash,
            createdAt: date('Y-m-d H:i:s'),
            updatedAt: date('Y-m-d H:i:s'),
        );
    }
}
