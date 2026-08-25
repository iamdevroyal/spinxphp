<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Persistence\Models;

use Spinx\Database\Model;

/**
 * Active Record model representing application users.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property string $created_at
 * @property string $updated_at
 */
final class User extends Model
{
    protected static string $table = 'users';

    protected array $fillable = [
        'name',
        'email',
        'password',
    ];

    protected array $hidden = [
        'password',
    ];
}
