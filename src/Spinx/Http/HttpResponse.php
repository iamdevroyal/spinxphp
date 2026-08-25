<?php

declare(strict_types=1);

namespace Spinx\Http;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Wraps Symfony's ResponseInterface with the Laravel Http-facade-style
 * convenience methods developers reach for constantly when calling a
 * third-party API: ->json(), ->successful(), ->status(), and so on,
 * instead of the more verbose Symfony equivalents.
 */
final class HttpResponse
{
    public function __construct(
        private readonly ResponseInterface $response,
    ) {
    }

    public function status(): int
    {
        return $this->response->getStatusCode();
    }

    public function successful(): bool
    {
        return $this->status() >= 200 && $this->status() < 300;
    }

    public function failed(): bool
    {
        return !$this->successful();
    }

    public function clientError(): bool
    {
        return $this->status() >= 400 && $this->status() < 500;
    }

    public function serverError(): bool
    {
        return $this->status() >= 500;
    }

    public function body(): string
    {
        return $this->response->getContent(false);
    }

    /** @return array<string, mixed> */
    public function json(): array
    {
        try {
            return $this->response->toArray(false);
        } catch (ExceptionInterface) {
            return [];
        }
    }

    /** Dot-notation access into the decoded JSON body, e.g. ->get('data.reference'). */
    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->json();

        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /** @return array<string, mixed> */
    public function headers(): array
    {
        try {
            return $this->response->getHeaders(false);
        } catch (ExceptionInterface) {
            return [];
        }
    }

    public function throw(): static
    {
        if ($this->failed()) {
            throw new \RuntimeException(sprintf(
                'HTTP request failed with status %d: %s',
                $this->status(),
                substr($this->body(), 0, 500)
            ));
        }

        return $this;
    }
}
