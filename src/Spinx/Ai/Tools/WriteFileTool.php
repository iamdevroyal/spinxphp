<?php

declare(strict_types=1);

namespace Spinx\Ai\Tools;

final class WriteFileTool implements ToolInterface
{
    public function __construct(private readonly string $projectRoot) {}

    public function getName(): string
    {
        return 'write_file';
    }

    public function getDescription(): string
    {
        return 'Write or overwrite a file in the project. Creates parent directories automatically.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'path'    => [
                    'type'        => 'string',
                    'description' => 'Relative path from project root (e.g. app/Modules/Billing/Domain/Entities/Plan.php)',
                ],
                'content' => [
                    'type'        => 'string',
                    'description' => 'Complete file contents to write',
                ],
            ],
            'required'   => ['path', 'content'],
        ];
    }

    public function execute(array $arguments): array
    {
        $path = $this->projectRoot . '/' . ltrim($arguments['path'], '/\\');
        $dir  = dirname($path);

        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $bytes = @file_put_contents($path, $arguments['content']);

        if ($bytes === false) {
            return ['error' => "Failed to write file: {$arguments['path']}"];
        }

        return [
            'success' => true,
            'path'    => $arguments['path'],
            'bytes'   => $bytes,
        ];
    }
}
