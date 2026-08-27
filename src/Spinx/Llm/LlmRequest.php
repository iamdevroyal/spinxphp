<?php

declare(strict_types=1);

namespace Spinx\Llm;

/**
 * Standardized multi-provider LLM request DTO.
 */
final class LlmRequest
{
    /**
     * @param ChatMessage[] $messages
     * @param array<int, array<string, mixed>> $tools
     */
    public function __construct(
        public readonly array $messages,
        public readonly ?string $model = null,
        public readonly ?string $system = null,
        public readonly array $tools = [],
        public readonly ?float $temperature = null,
        public readonly ?int $maxTokens = null,
        public readonly ?array $responseFormat = null,
    ) {
    }

    public static function fromPrompt(string $prompt, ?string $system = null): self
    {
        return new self(
            messages: [ChatMessage::user($prompt)],
            system: $system,
        );
    }
}
