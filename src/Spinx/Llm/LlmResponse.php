<?php

declare(strict_types=1);

namespace Spinx\Llm;

/**
 * Standardized multi-provider LLM response DTO.
 */
final class LlmResponse
{
    /**
     * @param array<int, array<string, mixed>> $toolCalls
     * @param array{input_tokens?: int, output_tokens?: int, total_tokens?: int} $usage
     */
    public function __construct(
        public readonly string $content,
        public readonly string $model,
        public readonly ?string $stopReason = null,
        public readonly array $toolCalls = [],
        public readonly array $usage = [],
        public readonly ?array $raw = null,
    ) {
    }

    public function text(): string
    {
        return $this->content;
    }

    public function hasToolCalls(): bool
    {
        return !empty($this->toolCalls);
    }

    public function inputTokens(): int
    {
        return (int) ($this->usage['input_tokens'] ?? 0);
    }

    public function outputTokens(): int
    {
        return (int) ($this->usage['output_tokens'] ?? 0);
    }

    /**
     * Parse the response content as JSON if structured output was requested.
     */
    public function json(): ?array
    {
        $decoded = @json_decode($this->content, true);
        return is_array($decoded) ? $decoded : null;
    }
}
