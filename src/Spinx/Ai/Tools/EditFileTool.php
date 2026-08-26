<?php

declare(strict_types=1);

namespace Spinx\Ai\Tools;

final class EditFileTool implements ToolInterface
{
    private const ALLOWED_PREFIXES = [
        'app/',
        'frontend/',
        'config/',
        'database/',
        'resources/',
        'storage/',
        'public/',
    ];

    private const BLOCKED_PATHS = [
        'src/Spinx/',
        'vendor/',
        'composer.json',
        'composer.lock',
    ];

    public function __construct(private readonly string $projectRoot) {}

    public function getName(): string
    {
        return 'edit_file';
    }

    public function getDescription(): string
    {
        return 'Replace an exact target string in an existing file. Allowed within app/, frontend/, config/, database/, resources/, storage/, public/. Modifying .env requires explicit dev permission.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'path' => [
                    'type'        => 'string',
                    'description' => 'Relative path to file (e.g. app/Modules/Auth/module.php, frontend/views/index.spinx.html)',
                ],
                'target' => [
                    'type'        => 'string',
                    'description' => 'Exact text to be replaced',
                ],
                'replacement' => [
                    'type'        => 'string',
                    'description' => 'New text to replace target',
                ],
                'dev_permission_granted' => [
                    'type'        => 'boolean',
                    'description' => 'Must be true if attempting to edit .env file with developer authorization',
                ],
            ],
            'required'   => ['path', 'target', 'replacement'],
        ];
    }

    public function execute(array $arguments): array
    {
        $rawPath = trim((string) ($arguments['path'] ?? ''));
        $relPath = ltrim(str_replace('\\', '/', $rawPath), '/');

        if (str_contains($relPath, '..')) {
            return ['error' => 'Path traversal ("..") is strictly prohibited.'];
        }

        if ($relPath === '.env' || $relPath === '.env.example') {
            $permission = (bool) ($arguments['dev_permission_granted'] ?? false);
            if (!$permission) {
                return [
                    'error' => 'Modifying .env requires explicit developer confirmation (dev_permission_granted = true). Please ask the developer for authorization first.',
                    'requires_permission' => true,
                    'file' => '.env',
                ];
            }
        } else {
            foreach (self::BLOCKED_PATHS as $blocked) {
                if (str_starts_with($relPath, $blocked) || $relPath === rtrim($blocked, '/')) {
                    return ['error' => "Editing protected framework directory or file [{$relPath}] is not permitted."];
                }
            }

            $isAllowed = false;
            foreach (self::ALLOWED_PREFIXES as $prefix) {
                if (str_starts_with($relPath, $prefix)) {
                    $isAllowed = true;
                    break;
                }
            }

            if (!$isAllowed) {
                return [
                    'error' => "Target path [{$relPath}] is outside editable sandbox. Allowed directories: " . implode(', ', self::ALLOWED_PREFIXES),
                ];
            }
        }

        $fullPath = $this->projectRoot . '/' . $relPath;

        if (!is_file($fullPath)) {
            return ['error' => "File not found: {$relPath}"];
        }

        $content = file_get_contents($fullPath);
        if (!str_contains($content, $arguments['target'])) {
            return ['error' => "Target string not found in file: {$relPath}"];
        }

        $newContent = str_replace($arguments['target'], $arguments['replacement'], $content);
        file_put_contents($fullPath, $newContent);

        return [
            'success' => true,
            'path'    => $relPath,
        ];
    }
}
