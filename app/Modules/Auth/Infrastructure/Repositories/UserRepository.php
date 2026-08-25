<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Repositories;

use App\Modules\Auth\Domain\Entities\User as UserEntity;
use App\Modules\Auth\Domain\Repositories\UserRepositoryInterface;
use App\Modules\Auth\Infrastructure\Persistence\Models\User as UserModel;

/**
 * Infrastructure repository implementing UserRepositoryInterface via Spinx Active Record Model.
 */
final class UserRepository implements UserRepositoryInterface
{
    public function findById(int|string $id): ?UserEntity
    {
        $model = UserModel::find((int) $id);

        return $model !== null ? $this->toEntity($model) : null;
    }

    public function findByEmail(string $email): ?UserEntity
    {
        $model = UserModel::query()
            ->where('email', '=', strtolower(trim($email)))
            ->first();

        return $model !== null ? $this->toEntity($model) : null;
    }

    public function existsByEmail(string $email): bool
    {
        return UserModel::query()
            ->where('email', '=', strtolower(trim($email)))
            ->exists();
    }

    public function save(UserEntity $user): UserEntity
    {
        if ($user->id !== null) {
            $model = UserModel::find($user->id);
            if ($model !== null) {
                $model->name = $user->name;
                $model->email = $user->email;
                $model->password = $user->passwordHash;
                $model->save();

                return $this->toEntity($model);
            }
        }

        $model = UserModel::create([
            'name'     => $user->name,
            'email'    => $user->email,
            'password' => $user->passwordHash,
        ]);

        return $this->toEntity($model);
    }

    public function delete(int|string $id): bool
    {
        $model = UserModel::find((int) $id);
        if ($model !== null) {
            $model->delete();
            return true;
        }

        return false;
    }

    private function toEntity(UserModel $model): UserEntity
    {
        return new UserEntity(
            id: (int) $model->id,
            name: (string) $model->name,
            email: (string) $model->email,
            passwordHash: (string) $model->password,
            createdAt: (string) $model->created_at,
            updatedAt: (string) $model->updated_at,
        );
    }
}
