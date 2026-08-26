<?php

declare(strict_types=1);

namespace Spinx\Ai\Tools;

use Spinx\Ai\Agents\AgentInterface;
use Spinx\Ai\Continuity\ContinuityTracker;

/**
 * Allows the Orchestrator (or any agent) to dynamically delegate specialized sub-tasks
 * to core Spinx agents (architect, database, routing, frontend, security, devops).
 */
final class DelegateToAgentTool implements ToolInterface
{
    /** @var \Closure(string): AgentInterface */
    private \Closure $agentResolver;

    /**
     * @param callable(string): AgentInterface $agentResolver
     */
    public function __construct(
        callable $agentResolver,
        private readonly ContinuityTracker $continuity,
    ) {
        $this->agentResolver = $agentResolver(...);
    }

    public function getName(): string
    {
        return 'delegate_to_agent';
    }

    public function getDescription(): string
    {
        return 'Delegate a specialized task to a Spinx core agent (architect, database, routing, frontend, security, devops). The sub-agent executes with its specialized domain knowledge and updates continuity memory.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'agent' => [
                    'type'        => 'string',
                    'enum'        => ['architect', 'database', 'routing', 'frontend', 'security', 'devops'],
                    'description' => 'Target agent specialized for the task',
                ],
                'task'  => [
                    'type'        => 'string',
                    'description' => 'Specific task instructions and requirements for the sub-agent',
                ],
            ],
            'required'   => ['agent', 'task'],
        ];
    }

    public function execute(array $arguments): array
    {
        $agentName = strtolower(trim((string) ($arguments['agent'] ?? '')));
        $task = trim((string) ($arguments['task'] ?? ''));

        if ($task === '') {
            return ['error' => 'Task instructions cannot be empty.'];
        }

        try {
            $resolver = $this->agentResolver;
            $agent = $resolver($agentName);
        } catch (\Throwable $e) {
            return ['error' => "Failed to resolve agent [{$agentName}]: " . $e->getMessage()];
        }

        // Sub-agent handles the delegated task
        $result = $agent->handle($task);

        // Record delegation in continuity tracker
        $this->continuity->recordAction(
            agent: $agentName,
            action: "Delegated task: {$task}",
            filesModified: array_column($result['steps'] ?? [], 'name')
        );

        return [
            'agent'    => $agentName,
            'task'     => $task,
            'success'  => true,
            'response' => $result['response'],
            'steps'    => $result['steps'] ?? [],
        ];
    }
}
