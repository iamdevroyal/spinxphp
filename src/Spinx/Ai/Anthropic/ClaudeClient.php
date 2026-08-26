<?php

declare(strict_types=1);

namespace Spinx\Ai\Anthropic;

use Spinx\Support\Config;

/**
 * Native Anthropic Claude API client supporting tool-calling and multi-turn conversations.
 */
final class ClaudeClient
{
    private string $apiKey;
    private string $model;
    private int $maxTokens;
    private int $timeout;

    public function __construct(
        ?string $apiKey = null,
        ?string $model = null,
        int $maxTokens = 8192,
        int $timeout = 120,
    ) {
        $this->apiKey    = $apiKey ?? (string) Config::get('ai.providers.anthropic.api_key', env('ANTHROPIC_API_KEY', ''));
        $this->model     = $model ?? (string) Config::get('ai.providers.anthropic.model', env('ANTHROPIC_MODEL', 'claude-sonnet-4-6'));
        $this->maxTokens = $maxTokens;
        $this->timeout   = $timeout;
    }

    /**
     * Send a request to the Anthropic Messages API.
     *
     * @param array<int, array{role: string, content: string|array}> $messages
     * @param array<int, array<string, mixed>> $tools
     * @return array{content: array, stop_reason: string, usage: array}
     */
    public function messages(
        array $messages,
        string $system = '',
        array $tools = [],
    ): array {
        if ($this->apiKey === '') {
            throw new \RuntimeException(
                'Anthropic API Key is missing. Set ANTHROPIC_API_KEY in your .env file or config/ai.php.'
            );
        }

        $payload = [
            'model'      => $this->model,
            'max_tokens' => $this->maxTokens,
            'messages'   => $messages,
        ];

        if ($system !== '') {
            $payload['system'] = $system;
        }

        if (!empty($tools)) {
            $payload['tools'] = $tools;
        }

        $response = $this->post('https://api.anthropic.com/v1/messages', $payload);

        return $response;
    }

    /**
     * Execute an HTTP POST to Anthropic API.
     */
    private function post(string $url, array $data): array
    {
        $json = json_encode($data, JSON_UNESCAPED_SLASHES);
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'x-api-key: ' . $this->apiKey,
                'anthropic-version: 2023-06-01',
            ],
        ]);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($result === false) {
            throw new \RuntimeException('Anthropic API request failed: ' . $error);
        }

        $decoded = @json_decode((string) $result, true);

        if ($httpCode >= 400 || !is_array($decoded)) {
            $errorMessage = $decoded['error']['message'] ?? "HTTP {$httpCode}: {$result}";
            throw new \RuntimeException("Anthropic API Error: {$errorMessage}");
        }

        return $decoded;
    }

    public function getModel(): string
    {
        return $this->model;
    }
}
