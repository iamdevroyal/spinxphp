<?php

declare(strict_types=1);

namespace Spinx\Http\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Base HTTP Exception representing an HTTP error status code.
 */
class HttpException extends RuntimeException
{
    public function __construct(
        protected int $statusCode,
        string $message = '',
        protected ?string $errorCode = null,
        protected ?array $errors = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    public function getErrors(): ?array
    {
        return $this->errors;
    }

    public function toProblemDetails(): ProblemDetails
    {
        $problem = ProblemDetails::create($this->statusCode, $this->getDefaultTitle(), $this->getMessage());

        if ($this->errorCode !== null) {
            $problem->withCode($this->errorCode);
        }

        if ($this->errors !== null) {
            $problem->withErrors($this->errors);
        }

        return $problem;
    }

    protected function getDefaultTitle(): string
    {
        return match ($this->statusCode) {
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            409 => 'Conflict',
            422 => 'Unprocessable Entity',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
            503 => 'Service Unavailable',
            default => 'HTTP Error',
        };
    }
}
