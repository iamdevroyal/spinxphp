<?php

declare(strict_types=1);

namespace Spinx\Http\Exceptions;

use JsonSerializable;
use Spinx\Http\JsonResponse;
use Spinx\Http\Response;

/**
 * ProblemDetails — RFC 7807 compliant Problem Details for HTTP APIs.
 *
 * Provides a standardized, machine-readable format for conveying API errors
 * across all client platforms (iOS, Android, React, Vue, Next.js, third-party integrations).
 *
 * Format:
 * {
 *   "type": "https://spinx.dev/errors/validation-failed",
 *   "title": "Validation Failed",
 *   "status": 422,
 *   "detail": "The email field is required.",
 *   "code": "VALIDATION_ERROR",
 *   "errors": { "email": ["The email field is required."] },
 *   "instance": "/api/v1/users",
 *   "request_id": "req_01j7abc123"
 * }
 */
final class ProblemDetails implements JsonSerializable
{
    public function __construct(
        public int $status,
        public string $title,
        public ?string $detail = null,
        public ?string $type = null,
        public ?string $code = null,
        public ?array $errors = null,
        public ?string $instance = null,
        public ?string $requestId = null,
        public array $extensions = [],
    ) {
        $this->type ??= 'about:blank';
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Fluent Factories
    // ─────────────────────────────────────────────────────────────────────────

    public static function create(int $status, string $title, ?string $detail = null): self
    {
        return new self($status, $title, $detail);
    }

    public static function notFound(string $detail = 'The requested resource was not found.', ?string $code = 'NOT_FOUND'): self
    {
        return new self(
            status: 404,
            title: 'Not Found',
            detail: $detail,
            code: $code,
        );
    }

    public static function unauthorized(string $detail = 'Authentication is required to access this resource.', ?string $code = 'UNAUTHORIZED'): self
    {
        return new self(
            status: 401,
            title: 'Unauthorized',
            detail: $detail,
            code: $code,
        );
    }

    public static function forbidden(string $detail = 'You do not have permission to perform this action.', ?string $code = 'FORBIDDEN'): self
    {
        return new self(
            status: 403,
            title: 'Forbidden',
            detail: $detail,
            code: $code,
        );
    }

    public static function validation(array $errors, string $detail = 'The given data failed validation.', ?string $code = 'VALIDATION_FAILED'): self
    {
        return new self(
            status: 422,
            title: 'Unprocessable Entity',
            detail: $detail,
            code: $code,
            errors: $errors,
        );
    }

    public static function badRequest(string $detail = 'The request payload is invalid.', ?string $code = 'BAD_REQUEST'): self
    {
        return new self(
            status: 400,
            title: 'Bad Request',
            detail: $detail,
            code: $code,
        );
    }

    public static function tooManyRequests(string $detail = 'Too many requests. Please slow down.', ?string $code = 'RATE_LIMIT_EXCEEDED'): self
    {
        return new self(
            status: 429,
            title: 'Too Many Requests',
            detail: $detail,
            code: $code,
        );
    }

    public static function serverError(string $detail = 'An unexpected internal server error occurred.', ?string $code = 'INTERNAL_SERVER_ERROR'): self
    {
        return new self(
            status: 500,
            title: 'Internal Server Error',
            detail: $detail,
            code: $code,
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Mutators
    // ─────────────────────────────────────────────────────────────────────────

    public function withCode(string $code): self
    {
        $this->code = $code;
        return $this;
    }

    public function withInstance(string $instance): self
    {
        $this->instance = $instance;
        return $this;
    }

    public function withRequestId(string $requestId): self
    {
        $this->requestId = $requestId;
        return $this;
    }

    public function withErrors(array $errors): self
    {
        $this->errors = $errors;
        return $this;
    }

    public function withExtension(string $key, mixed $value): self
    {
        $this->extensions[$key] = $value;
        return $this;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Serialization & HTTP Response
    // ─────────────────────────────────────────────────────────────────────────

    public function toArray(): array
    {
        $payload = [
            'type'   => $this->type,
            'title'  => $this->title,
            'status' => $this->status,
        ];

        if ($this->detail !== null) {
            $payload['detail'] = $this->detail;
        }

        if ($this->code !== null) {
            $payload['code'] = $this->code;
        }

        if (!empty($this->errors)) {
            $payload['errors'] = $this->errors;
        }

        if ($this->instance !== null) {
            $payload['instance'] = $this->instance;
        }

        if ($this->requestId !== null) {
            $payload['request_id'] = $this->requestId;
        }

        if (!empty($this->extensions)) {
            $payload = array_merge($payload, $this->extensions);
        }

        return $payload;
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Emit an RFC 7807 compliant HTTP Response (`Content-Type: application/problem+json`).
     */
    public function toResponse(array $headers = []): Response
    {
        $headers['Content-Type'] = 'application/problem+json';

        return Response::json($this->toArray(), $this->status, $headers);
    }
}
