<?php

declare(strict_types=1);

namespace Spinx\Http;

/**
 * Spinx JSON Response — extends Spinx\Http\Response for total type compatibility.
 *
 * Usage:
 *   return Response::json(['status' => 'ok'], 200);
 *   return new JsonResponse(['error' => 'Not found'], 404);
 *   return JsonResponse::success(['data' => $users]);
 *   return JsonResponse::error('Validation failed', 422, $errors);
 */
class JsonResponse extends Response
{
    protected mixed $data;
    protected int $encodingOptions = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES;

    public function __construct(mixed $data = null, int $status = 200, array $headers = [], bool $json = false)
    {
        parent::__construct('', $status, $headers);

        if ($json && is_string($data)) {
            $this->setJson($data);
        } else {
            $this->setData($data);
        }
    }

    public function setData(mixed $data = []): static
    {
        $this->data = $data;
        $this->content = json_encode($data, $this->encodingOptions);

        if ($this->content === false) {
            throw new \InvalidArgumentException(json_last_error_msg());
        }

        $this->headers->set('Content-Type', 'application/json');

        return $this;
    }

    public function setJson(string $json): static
    {
        $this->data = null;
        $this->content = $json;
        $this->headers->set('Content-Type', 'application/json');

        return $this;
    }

    public function getData(): mixed
    {
        return $this->data;
    }

    public function setEncodingOptions(int $encodingOptions): static
    {
        $this->encodingOptions = $encodingOptions;
        return $this->setData($this->data);
    }

    public function getEncodingOptions(): int
    {
        return $this->encodingOptions;
    }

    /**
     * Standard success envelope.
     */
    public static function success(mixed $data = null, int $status = 200, array $headers = []): static
    {
        return new static([
            'success' => true,
            'data'    => $data,
        ], $status, $headers);
    }

    /**
     * Standard error envelope.
     *
     * @param array<string, mixed>|null $errors
     */
    public static function error(string $message, int $status = 400, ?array $errors = null, array $headers = []): static
    {
        $body = ['success' => false, 'message' => $message];
        if ($errors !== null) {
            $body['errors'] = $errors;
        }

        return new static($body, $status, $headers);
    }

    /**
     * Standard 422 validation error envelope matching Spinx ValidationException shape.
     *
     * @param array<string, string[]> $errors
     */
    public static function validationError(array $errors, string $message = 'The given data was invalid.'): static
    {
        return new static([
            'success' => false,
            'message' => $message,
            'errors'  => $errors,
        ], 422);
    }

    /**
     * 401 Unauthorized envelope.
     */
    public static function unauthorized(string $message = 'Unauthenticated.'): static
    {
        return new static(['success' => false, 'message' => $message], 401);
    }

    /**
     * 403 Forbidden envelope.
     */
    public static function forbidden(string $message = 'This action is unauthorized.'): static
    {
        return new static(['success' => false, 'message' => $message], 403);
    }

    /**
     * 404 Not Found envelope.
     */
    public static function notFound(string $message = 'Resource not found.'): static
    {
        return new static(['success' => false, 'message' => $message], 404);
    }
}
