<?php

declare(strict_types=1);

namespace Spinx\Ai\Tools;

use Spinx\Ai\Continuity\ContinuityTracker;

/**
 * Registry holding tools available for Claude function calling.
 */
final class ToolRegistry
{
    /** @var array<string, ToolInterface> */
    private array $tools = [];
    private ContinuityTracker $continuity;

    /**
     * @param null|callable(string): \Spinx\Ai\Agents\AgentInterface $agentResolver
     */
    public function __construct(
        private readonly string $projectRoot,
        ?ContinuityTracker $continuity = null,
        ?callable $agentResolver = null,
    ) {
        $this->continuity = $continuity ?? new ContinuityTracker($this->projectRoot);
        $this->registerDefaultTools($agentResolver);
    }

    public function register(ToolInterface $tool): void
    {
        $this->tools[$tool->getName()] = $tool;
    }

    public function get(string $name): ?ToolInterface
    {
        return $this->tools[$name] ?? null;
    }

    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    /**
     * Export tools formatted for the Anthropic Messages API `tools` parameter.
     */
    public function toAnthropicSchema(): array
    {
        $schema = [];

        foreach ($this->tools as $tool) {
            $schema[] = [
                'name'        => $tool->getName(),
                'description' => $tool->getDescription(),
                'input_schema'=> $tool->getInputSchema(),
            ];
        }

        return $schema;
    }

    /**
     * Execute a tool by name with arguments.
     */
    public function execute(string $name, array $arguments): array
    {
        $tool = $this->get($name);

        if ($tool === null) {
            return ['error' => "Tool [{$name}] is not registered."];
        }

        try {
            $res = $tool->execute($arguments);

            // Automatically track file writes/edits in continuity memory
            if ($name === 'write_file' && ($res['success'] ?? false) && isset($arguments['path'])) {
                $this->continuity->recordFileChange('ai', (string) $arguments['path'], 'write');
            } elseif ($name === 'edit_file' && ($res['success'] ?? false) && isset($arguments['path'])) {
                $this->continuity->recordFileChange('ai', (string) $arguments['path'], 'edit');
            }

            return $res;
        } catch (\Throwable $e) {
            return ['error' => "Tool execution error: " . $e->getMessage()];
        }
    }

    private function registerDefaultTools(?callable $agentResolver): void
    {
        $this->register(new ReadFileTool($this->projectRoot));
        $this->register(new WriteFileTool($this->projectRoot));
        $this->register(new EditFileTool($this->projectRoot));
        $this->register(new ListDirectoryTool($this->projectRoot));
        $this->register(new SpinxCommandTool($this->projectRoot));
        $this->register(new CodeAnalyzerTool($this->projectRoot));
        $this->register(new ArchitectureValidatorTool());
        $this->register(new ProductionReadinessCheckTool($this->projectRoot, $this->continuity));

        if ($agentResolver !== null) {
            $this->register(new DelegateToAgentTool($agentResolver, $this->continuity));
        }
    }
}
