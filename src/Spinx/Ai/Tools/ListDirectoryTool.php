<?php

declare(strict_types=1);

namespace Spinx\Ai\Tools;

final class ListDirectoryTool implements ToolInterface
{
    public function __construct(private readonly string $projectRoot) {}

    public function getName(): string
    {
        return 'list_directory';
    }

    public function getDescription(): string
    {
        return 'List files and subdirectories in a given project directory.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'path' => [
                    'type'        => 'string',
                    'description' => 'Relative directory path from project root (default: "")',
                ],
            ],
        ];
    }

    public function execute(array $arguments): array
    {
        $relPath  = ltrim($arguments['path'] ?? '', '/\\');
        $fullPath = $relPath === '' ? $this->projectRoot : $this->projectRoot . '/' . $relPath;

        if (!is_dir($fullPath)) {
            return ['error' => "Directory not found: {$relPath}"];
        }

        $entries = scandir($fullPath) ?: [];
        $items   = [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === '.git') {
                continue;
            }

            $entryPath = $fullPath . '/' . $entry;
            $items[]   = [
                'name'  => $entry,
                'isDir' => is_dir($entryPath),
                'path'  => ($relPath !== '' ? $relPath . '/' : '') . $entry,
            ];
        }

        return ['directory' => $relPath, 'items' => $items];
    }
}
