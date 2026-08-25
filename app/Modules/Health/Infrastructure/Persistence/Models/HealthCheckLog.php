<?php

declare(strict_types=1);

namespace App\Modules\Health\Infrastructure\Persistence\Models;

use Spinx\Database\Model;

/**
 * Deliberately placed in Infrastructure/Persistence/Models rather than
 * Domain/Entities — an active-record Model is a persistence-layer
 * implementation detail (it knows about tables and columns), not a
 * Domain concept. A Domain\Entities\HealthCheck (plain PHP object, no
 * database awareness) would be the right home for actual business logic;
 * a repository in Infrastructure/Repositories would translate between
 * the two. This module keeps it simple since a health check has no real
 * domain logic to speak of — see the module README for the fuller
 * pattern once a module's domain actually needs it.
 */
final class HealthCheckLog extends Model
{
    protected static string $table = 'health_checks';

    protected array $fillable = ['status'];
}
