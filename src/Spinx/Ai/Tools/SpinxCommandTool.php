<?php

declare(strict_types=1);

namespace Spinx\Ai\Tools;

final class SpinxCommandTool implements ToolInterface
{
    public function __construct(private readonly string $projectRoot) {}

    public function getName(): string
    {
        return 'run_spinx_command';
    }

    public function getDescription(): string
    {
        return 'Run a Spinx CLI command (e.g. "make:module Billing --all", "migrate", "schema:compile", "cache:clear").';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'command' => [
                    'type'        => 'string',
                    'description' => 'The spinx CLI command and arguments (e.g. "make:module Billing --all")',
                ],
            ],
            'required'   => ['command'],
        ];
    }

    public function execute(array $arguments): array
    {
        $cmd = escapeshellcmd(PHP_BINARY . ' ' . escapeshellarg($this->projectRoot . '/spinx') . ' ' . $arguments['command']);
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($cmd, $descriptors, $pipes, $this->projectRoot);
        if (!is_resource($process)) {
            return ['error' => 'Failed to execute command.'];
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        return [
            'command'  => $arguments['command'],
            'exitCode' => $exitCode,
            'stdout'   => $stdout,
            'stderr'   => $stderr,
            'success'  => $exitCode === 0,
        ];
    }
}

final class CodeAnalyzerTool implements ToolInterface
{
    public function __construct(private readonly string $projectRoot) {}

    public function getName(): string
    {
        return 'analyze_code';
    }

    public function getDescription(): string
    {
        return 'Perform PHP syntax check and Spinx strict DDD compliance analysis on a file.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'path' => ['type' => 'string', 'description' => 'Relative path to PHP file'],
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

        // 1. PHP Syntax Check
        $lintCmd = escapeshellcmd(PHP_BINARY . ' -l ' . escapeshellarg($path));
        $output = (string) shell_exec($lintCmd);
        $hasSyntaxError = !str_contains($output, 'No syntax errors detected');

        if ($hasSyntaxError) {
            return [
                'valid' => false,
                'lint'  => trim($output),
            ];
        }

        // 2. DDD Structural Checks
        $issues = [];
        $content = (string) file_get_contents($path);

        if (str_contains($path, '/Domain/') && (str_contains($content, 'Symfony\\') || str_contains($content, 'Doctrine\\') || str_contains($content, 'Model'))) {
            $issues[] = 'Domain layer contains external infrastructure dependencies (Symfony/DBAL/Model). Keep Domain pure!';
        }

        if (str_contains($path, '/Controllers/') && str_contains($content, 'Symfony\\Component\\HttpFoundation\\Response')) {
            $issues[] = 'Controller imports raw Symfony Response instead of Spinx\\Http\\Response.';
        }

        return [
            'valid'  => empty($issues) && !$hasSyntaxError,
            'lint'   => 'No syntax errors detected.',
            'issues' => $issues,
        ];
    }
}
