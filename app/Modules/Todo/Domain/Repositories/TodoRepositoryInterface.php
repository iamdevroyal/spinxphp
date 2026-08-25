<?php

declare(strict_types=1);

namespace App\Modules\Todo\Domain\Repositories;

use App\Modules\Todo\Domain\Entities\Todo;

/**
 * Domain repository contract for Todo persistence.
 */
interface TodoRepositoryInterface
{
    /** @return Todo[] */
    public function all(): array;

    public function findById(int|string $id): ?Todo;

    public function save(Todo $todo): Todo;

    public function delete(int|string $id): bool;
}
