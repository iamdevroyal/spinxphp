<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain\Repositories;

use App\Modules\Auth\Domain\Entities\User;

/**
 * Domain repository contract for User persistence and querying.
 */
interface UserRepositoryInterface
{
    public function findById(int|string $id): ?User;

    public function findByEmail(string $email): ?User;

    public function existsByEmail(string $email): bool;

    public function save(User $user): User;

    public function delete(int|string $id): bool;
}
