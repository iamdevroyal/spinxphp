<?php

declare(strict_types=1);

namespace Spinx\Llm;

/**
 * Standardized multi-provider chat message value object.
 */
final class ChatMessage
{
    public function __construct(
        public readonly string $role, // 'system' | 'user' | 'assistant' | 'tool'
        public readonly string|array $content,
        public readonly ?string $name = null,
        public readonly ?array $toolCalls = null,
        public readonly ?string $toolCallId = null,
    ) {
    }

    public static function system(string $content): self
    {
        return new self('system', $content);
    }

    public static function user(string|array $content): self
    {
        return new self('user', $content);
    }

    public static function assistant(string|array $content, ?array $toolCalls = null): self
    {
        return new self('assistant', $content, null, $toolCalls);
    }

    public static function tool(string $toolCallId, string $content): self
    {
        return new self('tool', $content, null, null, $toolCallId);
    }

    public function toArray(): array
    {
        $arr = [
            'role'    => $this->role,
            'content' => $this->content,
        ];

        if ($this->name !== null) {
            $arr['name'] = $this->name;
        }

        if ($this->toolCalls !== null) {
            $arr['tool_calls'] = $this->toolCalls;
        }

        if ($this->toolCallId !== null) {
            $arr['tool_call_id'] = $this->toolCallId;
        }

        return $arr;
    }
}
