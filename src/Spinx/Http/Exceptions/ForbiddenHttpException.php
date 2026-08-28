<?php

declare(strict_types=1);

namespace Spinx\Http\Exceptions;

use Throwable;

final class ForbiddenHttpException extends HttpException
{
    public function __construct(string $message = 'This action is unauthorized.', ?string $errorCode = 'FORBIDDEN', ?Throwable $previous = null)
    {
        parent::__construct(403, $message, $errorCode, null, $previous);
    }
}
