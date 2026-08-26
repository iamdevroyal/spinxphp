<?php

declare(strict_types=1);

namespace Spinx\Ai\Agents;

use Spinx\Ai\Anthropic\ClaudeClient;
use Spinx\Ai\Continuity\ContinuityTracker;
use Spinx\Ai\Tools\ToolRegistry;

abstract class AbstractAgent implements AgentInterface
{
    public function __construct(
        protected readonly ClaudeClient $client,
        protected readonly ToolRegistry $tools,
        protected readonly ContinuityTracker $continuity,
    ) {
    }

    /**
     * Autonomous conversation loop with Claude tool calling.
     */
    public function handle(string $prompt, array $conversationHistory = [], ?callable $onStep = null): array
    {
        $messages   = $conversationHistory;
        $messages[] = ['role' => 'user', 'content' => $prompt];

        $systemPrompt  = $this->getSystemPrompt();
        $toolSchema    = $this->tools->toAnthropicSchema();
        $steps         = [];
        $contentBlocks = [];
        $maxTurns      = 12;

        for ($turn = 0; $turn < $maxTurns; $turn++) {
            $response      = $this->client->messages($messages, $systemPrompt, $toolSchema);
            $contentBlocks = $response['content'] ?? [];
            $messages[]    = ['role' => 'assistant', 'content' => $contentBlocks];

            $hasToolUse  = false;
            $toolResults = [];

            foreach ($contentBlocks as $block) {
                if ($block['type'] === 'text') {
                    $steps[] = ['type' => 'text', 'agent' => $this->getName(), 'text' => $block['text']];
                    if ($onStep !== null) {
                        $onStep('text', $block['text'], $this->getName());
                    }
                } elseif ($block['type'] === 'tool_use') {
                    $hasToolUse = true;
                    $toolName   = $block['name'];
                    $toolInput  = $block['input'] ?? [];
                    $toolUseId  = $block['id'];

                    if ($onStep !== null) {
                        $onStep('tool_call', "Executing tool [{$toolName}]", $this->getName());
                    }

                    $execResult = $this->tools->execute($toolName, $toolInput);

                    $steps[] = [
                        'type'   => 'tool',
                        'name'   => $toolName,
                        'input'  => $toolInput,
                        'result' => $execResult,
                    ];

                    $toolResults[] = [
                        'type'        => 'tool_result',
                        'tool_use_id' => $toolUseId,
                        'content'     => json_encode($execResult, JSON_UNESCAPED_SLASHES),
                    ];
                }
            }

            if (!$hasToolUse || ($response['stop_reason'] ?? '') === 'end_turn') {
                break;
            }

            if (!empty($toolResults)) {
                $messages[] = ['role' => 'user', 'content' => $toolResults];
            }
        }

        $this->continuity->recordAction($this->getName(), $prompt);

        return [
            'agent'    => $this->getName(),
            'messages' => $messages,
            'steps'    => $steps,
            'response' => $this->extractFinalText($contentBlocks),
        ];
    }

    protected function extractFinalText(array $contentBlocks): string
    {
        $texts = [];
        foreach ($contentBlocks as $block) {
            if ($block['type'] === 'text') {
                $texts[] = $block['text'];
            }
        }
        return implode("\n", $texts);
    }
}
