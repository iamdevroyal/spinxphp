<?php

declare(strict_types=1);

namespace App\Modules\Todo\Application\Services;

use App\Modules\Todo\Domain\Entities\Todo;
use App\Modules\Todo\Domain\Repositories\TodoRepositoryInterface;

/**
 * Application Service for Todo business operations.
 */
final class TodoService
{
    public function __construct(
        private readonly TodoRepositoryInterface $repository,
    ) {
    }

    /** @return Todo[] */
    public function listTodos(): array
    {
        return $this->repository->all();
    }

    public function createTodo(string $title): Todo
    {
        $title = trim($title);
        if ($title === '') {
            throw new \InvalidArgumentException('Todo title cannot be empty.');
        }

        $todo = Todo::create($title);

        return $this->repository->save($todo);
    }

    public function toggleTodo(int|string $id): ?Todo
    {
        $todo = $this->repository->findById($id);
        if ($todo === null) {
            return null;
        }

        $toggled = $todo->withToggledStatus();

        return $this->repository->save($toggled);
    }

    public function deleteTodo(int|string $id): bool
    {
        return $this->repository->delete($id);
    }
}
