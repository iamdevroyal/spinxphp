<?php

declare(strict_types=1);

namespace Spinx\Http\Exceptions;

use Throwable;

final class TooManyRequestsHttpException extends HttpException
{
    public function __construct(string $message = 'Too many requests. Please slow down.', ?string $errorCode = 'RATE_LIMIT_EXCEEDED', ?Throwable $previous = null)
    {
        parent::__construct(429, $message, $errorCode, null, $previous);
    }
}
