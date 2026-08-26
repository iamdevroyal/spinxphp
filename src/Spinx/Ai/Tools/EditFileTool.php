<?php

declare(strict_types=1);

namespace Spinx\Ai\Tools;

final class EditFileTool implements ToolInterface
{
    public function __construct(private readonly string $projectRoot) {}

    public function getName(): string
    {
        return 'edit_file';
    }

    public function getDescription(): string
    {
        return 'Replace an exact target string in an existing file with new content.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'path'        => ['type' => 'string', 'description' => 'Relative path to file'],
                'target'      => ['type' => 'string', 'description' => 'Exact text to be replaced'],
                'replacement' => ['type' => 'string', 'description' => 'New text to replace target'],
            ],
            'required'   => ['path', 'target', 'replacement'],
        ];
    }

    public function execute(array $arguments): array
    {
        $path = $this->projectRoot . '/' . ltrim($arguments['path'], '/\\');

        if (!is_file($path)) {
            return ['error' => "File not found: {$arguments['path']}"];
        }

        $content = file_get_contents($path);
        if (!str_contains($content, $arguments['target'])) {
            return ['error' => "Target string not found in file: {$arguments['path']}"];
        }

        $newContent = str_replace($arguments['target'], $arguments['replacement'], $content);
        file_put_contents($path, $newContent);

        return [
            'success' => true,
            'path'    => $arguments['path'],
        ];
    }
}
