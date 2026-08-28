<?php

declare(strict_types=1);

namespace Spinx\Http\Exceptions;

use Throwable;

final class NotFoundHttpException extends HttpException
{
    public function __construct(string $message = 'Resource not found.', ?string $errorCode = 'NOT_FOUND', ?Throwable $previous = null)
    {
        parent::__construct(404, $message, $errorCode, null, $previous);
    }
}
