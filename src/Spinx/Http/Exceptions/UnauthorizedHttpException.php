<?php

declare(strict_types=1);

namespace Spinx\Http\Exceptions;

use Throwable;

final class UnauthorizedHttpException extends HttpException
{
    public function __construct(string $message = 'Authentication required.', ?string $errorCode = 'UNAUTHORIZED', ?Throwable $previous = null)
    {
        parent::__construct(401, $message, $errorCode, null, $previous);
    }
}
