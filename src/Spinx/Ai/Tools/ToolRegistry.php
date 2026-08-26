<?php

declare(strict_types=1);

namespace Spinx\Ai\Tools;

/**
 * Registry holding tools available for Claude function calling.
 */
final class ToolRegistry
{
    /** @var array<string, ToolInterface> */
    private array $tools = [];

    public function __construct(
        private readonly string $projectRoot,
    ) {
        $this->registerDefaultTools();
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
            return $tool->execute($arguments);
        } catch (\Throwable $e) {
            return ['error' => "Tool execution error: " . $e->getMessage()];
        }
    }

    private function registerDefaultTools(): void
    {
        $this->register(new ReadFileTool($this->projectRoot));
        $this->register(new WriteFileTool($this->projectRoot));
        $this->register(new EditFileTool($this->projectRoot));
        $this->register(new ListDirectoryTool($this->projectRoot));
        $this->register(new SpinxCommandTool($this->projectRoot));
        $this->register(new CodeAnalyzerTool($this->projectRoot));
    }
}
