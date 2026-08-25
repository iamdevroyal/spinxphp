<?php

declare(strict_types=1);

namespace App\Modules\Todo\Infrastructure\Persistence\Models;

use Spinx\Database\Model;

final class Todo extends Model
{
    protected static string $table = 'todos';

    protected array $fillable = ['title', 'done'];

    protected array $casts = ['done' => 'bool'];
}
