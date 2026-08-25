<?php

declare(strict_types=1);

/** Read by Spinx\Http\Middleware\RateLimitMiddleware. */
return [
    'max_attempts' => (int) env('RATE_LIMIT_MAX_ATTEMPTS', 60),
    'decay_seconds' => (int) env('RATE_LIMIT_DECAY_SECONDS', 60),
];
