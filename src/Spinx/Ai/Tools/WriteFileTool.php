<?php

declare(strict_types=1);

namespace Spinx\Ai\Tools;

final class WriteFileTool implements ToolInterface
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
        return 'write_file';
    }

    public function getDescription(): string
    {
        return 'Write or overwrite a file in the project. Allowed within app/, frontend/, config/, database/, resources/, storage/, public/. Modifying .env requires explicit dev permission.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'path' => [
                    'type'        => 'string',
                    'description' => 'Relative path from project root (e.g. app/Modules/Billing/Domain/Entities/Plan.php, frontend/views/checkout.spinx.html)',
                ],
                'content' => [
                    'type'        => 'string',
                    'description' => 'Complete file contents to write',
                ],
                'dev_permission_granted' => [
                    'type'        => 'boolean',
                    'description' => 'Must be true if attempting to write or modify .env file with developer authorization',
                ],
            ],
            'required'   => ['path', 'content'],
        ];
    }

    public function execute(array $arguments): array
    {
        $rawPath = trim((string) ($arguments['path'] ?? ''));
        $relPath = ltrim(str_replace('\\', '/', $rawPath), '/');

        // Check for directory traversal attempts
        if (str_contains($relPath, '..')) {
            return ['error' => 'Path traversal ("..") is strictly prohibited.'];
        }

        // Handle .env file write check
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
            // Check blocked paths
            foreach (self::BLOCKED_PATHS as $blocked) {
                if (str_starts_with($relPath, $blocked) || $relPath === rtrim($blocked, '/')) {
                    return ['error' => "Writing to protected framework directory or file [{$relPath}] is not permitted."];
                }
            }

            // Check allowed prefixes
            $isAllowed = false;
            foreach (self::ALLOWED_PREFIXES as $prefix) {
                if (str_starts_with($relPath, $prefix)) {
                    $isAllowed = true;
                    break;
                }
            }

            if (!$isAllowed) {
                return [
                    'error' => "Target path [{$relPath}] is outside writable sandbox. Allowed directories: " . implode(', ', self::ALLOWED_PREFIXES),
                ];
            }
        }

        $fullPath = $this->projectRoot . '/' . $relPath;
        $dir = dirname($fullPath);

        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $bytes = @file_put_contents($fullPath, $arguments['content'] ?? '');

        if ($bytes === false) {
            return ['error' => "Failed to write file: {$relPath}"];
        }

        return [
            'success' => true,
            'path'    => $relPath,
            'bytes'   => $bytes,
        ];
    }
}
