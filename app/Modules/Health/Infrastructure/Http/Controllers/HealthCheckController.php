<?php

declare(strict_types=1);

namespace App\Modules\Health\Infrastructure\Http\Controllers;

use App\Modules\Health\Infrastructure\Persistence\Models\HealthCheckLog;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Reference controller proving the kernel/routing/DI wiring from build
 * step 1 works end to end. Controllers live ONLY here — under a module's
 * Infrastructure/Http/Controllers — never in a bare app/Controllers dir.
 *
 * No #[Singleton] attribute here deliberately: this controller is
 * request-scoped by default (build spec §4), which is correct since it
 * holds no state at all. That's the common case — most controllers should
 * stay on the default rather than opting into Singleton.
 */
final class HealthCheckController
{
    public function __invoke(Request $request): JsonResponse
    {
        HealthCheckLog::create(['status' => 'ok']);

        return new JsonResponse([
            'status' => 'ok',
            'driver' => 'roadrunner',
            'module' => 'Health',
        ]);
    }
}
