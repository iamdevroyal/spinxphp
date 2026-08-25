<?php

declare(strict_types=1);

namespace Spinx\Http;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The framework's answer to "how do I pull in an external API/service" —
 * autowire this into any Application service or controller and call
 * ->get()/->post()/etc. Credentials come from config('services.*'),
 * which comes from .env (see config/services.php) — nothing here hits
 * .env or a config file directly, keeping this class a pure HTTP
 * concern. See docs/external-services.md for a complete, working
 * Paystack integration built from this exact pattern.
 *
 * Fluent config methods (withToken, withHeaders, baseUrl) return a NEW
 * instance rather than mutating $this, so a single injected HttpClient
 * can be safely reused/reconfigured per-call without one call's headers
 * leaking into another's.
 */
final class HttpClient
{
    private string $baseUrl = '';

    /** @var array<string, string> */
    private array $headers = [];

    public function __construct(
        private readonly HttpClientInterface $client,
    ) {
    }

    public function baseUrl(string $url): static
    {
        $clone = clone $this;
        $clone->baseUrl = rtrim($url, '/');

        return $clone;
    }

    /** @param array<string, string> $headers */
    public function withHeaders(array $headers): static
    {
        $clone = clone $this;
        $clone->headers = [...$this->headers, ...$headers];

        return $clone;
    }

    public function withToken(string $token, string $type = 'Bearer'): static
    {
        return $this->withHeaders(['Authorization' => "{$type} {$token}"]);
    }

    /** @param array<string, mixed> $query */
    public function get(string $path, array $query = []): HttpResponse
    {
        return $this->request('GET', $path, ['query' => $query]);
    }

    /** @param array<string, mixed> $data */
    public function post(string $path, array $data = []): HttpResponse
    {
        return $this->request('POST', $path, ['json' => $data]);
    }

    /** @param array<string, mixed> $data */
    public function put(string $path, array $data = []): HttpResponse
    {
        return $this->request('PUT', $path, ['json' => $data]);
    }

    /** @param array<string, mixed> $data */
    public function patch(string $path, array $data = []): HttpResponse
    {
        return $this->request('PATCH', $path, ['json' => $data]);
    }

    public function delete(string $path): HttpResponse
    {
        return $this->request('DELETE', $path);
    }

    /** @param array<string, mixed> $options */
    private function request(string $method, string $path, array $options = []): HttpResponse
    {
        $url = $this->baseUrl === '' ? $path : $this->baseUrl . '/' . ltrim($path, '/');

        if ($this->headers !== []) {
            $options['headers'] = [...($options['headers'] ?? []), ...$this->headers];
        }

        return new HttpResponse($this->client->request($method, $url, $options));
    }
}
