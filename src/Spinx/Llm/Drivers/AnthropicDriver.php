<?php

declare(strict_types=1);

namespace Spinx\Llm\Drivers;

use Spinx\Llm\ChatMessage;
use Spinx\Llm\Contracts\LlmProviderInterface;
use Spinx\Llm\LlmRequest;
use Spinx\Llm\LlmResponse;
use Spinx\Support\Config;

/**
 * Anthropic Claude driver (Claude 3.5 Sonnet, Claude 3 Opus, Haiku).
 */
final class AnthropicDriver implements LlmProviderInterface
{
    private string $apiKey;
    private string $defaultModel;

    public function __construct(?array $config = null)
    {
        $cfg = $config ?? (array) Config::get('llm.providers.anthropic', []);

        $this->apiKey       = (string) ($cfg['api_key'] ?? env('ANTHROPIC_API_KEY', ''));
        $this->defaultModel = (string) ($cfg['model'] ?? env('ANTHROPIC_MODEL', 'claude-3-5-sonnet-20241022'));
    }

    public function generate(LlmRequest $request): LlmResponse
    {
        if ($this->apiKey === '') {
            throw new \RuntimeException('Anthropic API key is missing.');
        }

        $model = $request->model ?? $this->defaultModel;
        $system = $request->system ?? '';

        $messages = [];
        foreach ($request->messages as $msg) {
            if ($msg->role === 'system') {
                $system .= ($system !== '' ? "\n" : '') . (string) $msg->content;
                continue;
            }

            $messages[] = [
                'role'    => $msg->role,
                'content' => $msg->content,
            ];
        }

        $payload = [
            'model'      => $model,
            'max_tokens' => $request->maxTokens ?? 4096,
            'messages'   => $messages,
        ];

        if ($system !== '') {
            $payload['system'] = $system;
        }

        if ($request->temperature !== null) {
            $payload['temperature'] = $request->temperature;
        }

        if (!empty($request->tools)) {
            $payload['tools'] = $request->tools;
        }

        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_SLASHES),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'x-api-key: ' . $this->apiKey,
                'anthropic-version: 2023-06-01',
            ],
        ]);

        $result = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($result === false || $httpCode >= 400) {
            throw new \RuntimeException("Anthropic API Error [HTTP {$httpCode}]: {$error} {$result}");
        }

        $decoded = json_decode((string) $result, true, 512, JSON_THROW_ON_ERROR);

        $textBlocks = [];
        $toolCalls = [];

        foreach ($decoded['content'] ?? [] as $block) {
            if ($block['type'] === 'text') {
                $textBlocks[] = $block['text'];
            } elseif ($block['type'] === 'tool_use') {
                $toolCalls[] = [
                    'id'   => $block['id'],
                    'name' => $block['name'],
                    'args' => $block['input'] ?? [],
                ];
            }
        }

        $usage = [
            'input_tokens'  => (int) ($decoded['usage']['input_tokens'] ?? 0),
            'output_tokens' => (int) ($decoded['usage']['output_tokens'] ?? 0),
        ];

        return new LlmResponse(
            content: implode("\n", $textBlocks),
            model: (string) ($decoded['model'] ?? $model),
            stopReason: $decoded['stop_reason'] ?? null,
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
