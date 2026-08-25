<?php

declare(strict_types=1);

namespace App\Modules\Todo\Infrastructure\Repositories;

use App\Modules\Todo\Domain\Entities\Todo as TodoEntity;
use App\Modules\Todo\Domain\Repositories\TodoRepositoryInterface;
use App\Modules\Todo\Infrastructure\Persistence\Models\Todo as TodoModel;

/**
 * Infrastructure repository implementing TodoRepositoryInterface.
 */
final class TodoRepository implements TodoRepositoryInterface
{
    /** @return TodoEntity[] */
    public function all(): array
    {
        $models = TodoModel::query()->orderBy('id', 'DESC')->get();

        return array_map(fn (TodoModel $m) => $this->toEntity($m), $models);
    }

    public function findById(int|string $id): ?TodoEntity
    {
        $model = TodoModel::find((int) $id);

        return $model !== null ? $this->toEntity($model) : null;
    }

    public function save(TodoEntity $todo): TodoEntity
    {
        if ($todo->id !== null) {
            $model = TodoModel::find($todo->id);
            if ($model !== null) {
                $model->title = $todo->title;
                $model->done  = $todo->done;
                $model->save();

                return $this->toEntity($model);
            }
        }

        $model = TodoModel::create([
            'title' => $todo->title,
            'done'  => $todo->done,
        ]);

        return $this->toEntity($model);
    }

    public function delete(int|string $id): bool
    {
        $model = TodoModel::find((int) $id);
        if ($model !== null) {
            $model->delete();
            return true;
        }

        return false;
    }

    private function toEntity(TodoModel $model): TodoEntity
    {
        return new TodoEntity(
            id: (int) $model->id,
            title: (string) $model->title,
            done: (bool) $model->done,
            createdAt: (string) $model->created_at,
            updatedAt: (string) $model->updated_at,
        );
    }
}
