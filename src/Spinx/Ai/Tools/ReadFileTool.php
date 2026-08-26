<?php

declare(strict_types=1);

namespace Spinx\Ai\Tools;

final class ReadFileTool implements ToolInterface
{
    public function __construct(private readonly string $projectRoot) {}

    public function getName(): string
    {
        return 'read_file';
    }

    public function getDescription(): string
    {
        return 'Read the text content of a file in the project.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'path' => [
                    'type'        => 'string',
                    'description' => 'Relative path from project root (e.g. app/Modules/Auth/module.php)',
                ],
            ],
            'required'   => ['path'],
        ];
    }

    public function execute(array $arguments): array
    {
        $path = $this->projectRoot . '/' . ltrim($arguments['path'], '/\\');

        if (!is_file($path)) {
            return ['error' => "File not found: {$arguments['path']}"];
        }

        $content = file_get_contents($path);
        return [
            'path'    => $arguments['path'],
            'content' => $content,
        ];
    }
}
