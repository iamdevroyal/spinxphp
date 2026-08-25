<?php

declare(strict_types=1);

namespace Spinx\Auth;

use Spinx\Database\Model;

/**
 * Default user provider backed by a Spinx Model subclass.
 *
 * Configured via config/auth.php:
 *
 *   'model' => \App\Modules\Users\Infrastructure\Persistence\Models\User::class,
 *   'password_field' => 'password',
 *
 * The model is resolved lazily (not at boot) so this provider can be
 * registered in the container before the database connection is established.
 */
final class EloquentUserProvider implements UserProviderInterface
{
    /** @param class-string<Model> $modelClass */
    public function __construct(
        private readonly string $modelClass,
        private readonly string $passwordField = 'password',
    ) {
    }

    public function findById(int|string $id): ?object
    {
        /** @var class-string<Model> $modelClass */
        $modelClass = $this->modelClass;

        return $modelClass::find($id);
    }

    public function findByCredentials(array $credentials): ?object
    {
        /** @var class-string<Model> $modelClass */
        $modelClass = $this->modelClass;
        $query      = $modelClass::query();

        // Add a WHERE clause for each credential except the password field —
        // the password must never be sent to the database in plaintext.
        foreach ($credentials as $field => $value) {
            if ($field !== $this->passwordField) {
                $query->where($field, $value);
            }
        }

        return $query->first();
    }

    public function validateCredentials(object $user, string $password): bool
    {
        $hash = $user->{$this->passwordField} ?? null;

        if (!is_string($hash) || $hash === '') {
            return false;
        }

        return password_verify($password, $hash);
    }
}
