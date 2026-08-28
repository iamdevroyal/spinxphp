<?php

declare(strict_types=1);

namespace Spinx\Http\Exceptions;

use Throwable;

final class BadRequestHttpException extends HttpException
{
    public function __construct(string $message = 'Bad request.', ?string $errorCode = 'BAD_REQUEST', ?array $errors = null, ?Throwable $previous = null)
    {
        parent::__construct(400, $message, $errorCode, $errors, $previous);
    }
}
