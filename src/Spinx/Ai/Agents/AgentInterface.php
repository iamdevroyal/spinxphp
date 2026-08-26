<?php

declare(strict_types=1);

namespace Spinx\Ai\Agents;

interface AgentInterface
{
    public function getName(): string;
    public function getDescription(): string;
    public function getSystemPrompt(): string;
    public function handle(string $prompt, array $conversationHistory = [], ?callable $onStep = null): array;
}
