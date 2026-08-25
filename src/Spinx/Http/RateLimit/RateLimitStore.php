<?php

declare(strict_types=1);

namespace Spinx\Http\RateLimit;

interface RateLimitStore
{
    public function attempts(string $key): int;

    /** @return int The new attempt count after incrementing */
    public function increment(string $key, int $decaySeconds): int;

    /** Seconds until this key's window resets. */
    public function availableIn(string $key): int;
}
