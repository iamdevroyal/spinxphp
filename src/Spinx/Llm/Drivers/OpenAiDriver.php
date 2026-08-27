<?php

declare(strict_types=1);

namespace Spinx\Llm\Drivers;

use Spinx\Llm\ChatMessage;
use Spinx\Llm\Contracts\LlmProviderInterface;
use Spinx\Llm\LlmRequest;
use Spinx\Llm\LlmResponse;
use Spinx\Support\Config;

/**
 * OpenAI & OpenAI-compatible driver (GPT-4o, GPT-4o-mini, o1, Ollama, Groq, DeepSeek).
 */
final class OpenAiDriver implements LlmProviderInterface
{
    private string $apiKey;
    private string $defaultModel;
    private string $baseUrl;

    public function __construct(?array $config = null)
    {
        $cfg = $config ?? (array) Config::get('llm.providers.openai', []);

        $this->apiKey       = (string) ($cfg['api_key'] ?? env('OPENAI_API_KEY', ''));
        $this->defaultModel = (string) ($cfg['model'] ?? env('OPENAI_MODEL', 'gpt-4o'));
        $this->baseUrl      = rtrim((string) ($cfg['base_url'] ?? env('OPENAI_BASE_URL', 'https://api.openai.com/v1')), '/');
    }

    public function generate(LlmRequest $request): LlmResponse
    {
        if ($this->apiKey === '' && !str_contains($this->baseUrl, 'localhost') && !str_contains($this->baseUrl, '127.0.0.1')) {
            throw new \RuntimeException('OpenAI API key is missing.');
        }

        $model = $request->model ?? $this->defaultModel;
        $messages = [];

        if ($request->system !== null && $request->system !== '') {
            $messages[] = ['role' => 'system', 'content' => $request->system];
        }

        foreach ($request->messages as $msg) {
            $messages[] = [
                'role'    => $msg->role,
                'content' => $msg->content,
            ];
        }

        $payload = [
            'model'    => $model,
            'messages' => $messages,
        ];

        if ($request->temperature !== null) {
            $payload['temperature'] = $request->temperature;
        }

        if ($request->maxTokens !== null) {
            $payload['max_tokens'] = $request->maxTokens;
        }

        if (!empty($request->tools)) {
            $payload['tools'] = $request->tools;
        }

        if ($request->responseFormat !== null) {
            $payload['response_format'] = $request->responseFormat;
        }

        $ch = curl_init("{$this->baseUrl}/chat/completions");
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_SLASHES),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
        ]);

        $result = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($result === false || $httpCode >= 400) {
            throw new \RuntimeException("OpenAI API Error [HTTP {$httpCode}]: {$error} {$result}");
        }

        $decoded = json_decode((string) $result, true, 512, JSON_THROW_ON_ERROR);

        $choice = $decoded['choices'][0] ?? [];
        $message = $choice['message'] ?? [];
        $content = (string) ($message['content'] ?? '');

        $toolCalls = [];
        foreach ($message['tool_calls'] ?? [] as $call) {
            $toolCalls[] = [
                'id'   => $call['id'] ?? '',
                'name' => $call['function']['name'] ?? '',
                'args' => json_decode((string) ($call['function']['arguments'] ?? '{}'), true) ?: [],
            ];
        }

        $usage = [
            'input_tokens'  => (int) ($decoded['usage']['prompt_tokens'] ?? 0),
            'output_tokens' => (int) ($decoded['usage']['completion_tokens'] ?? 0),
            'total_tokens'  => (int) ($decoded['usage']['total_tokens'] ?? 0),
        ];

        return new LlmResponse(
            content: $content,
            model: (string) ($decoded['model'] ?? $model),
            stopReason: $choice['finish_reason'] ?? null,
            toolCalls: $toolCalls,
            usage: $usage,
            raw: $decoded
        );
    }

    public function stream(LlmRequest $request): \Generator
    {
        $response = $this->generate($request);
        yield $response->content;
    }
}
