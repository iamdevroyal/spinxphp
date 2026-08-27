<?php

declare(strict_types=1);

namespace Spinx\Ai\Tools;

final class SpinxCommandTool implements ToolInterface
{
    private const ALLOWED_COMMANDS = [
        'make:module',
        'make:migration',
        'make:model',
        'make:controller',
        'migrate',
        'migrate:rollback',
        'migrate:fresh',
        'schema:compile',
        'cache:clear',
        'optimize',
        'route:list',
        'schedule:run',
        'queue:work',
        'ai:build',
        'ai:chat',
    ];

    public function __construct(private readonly string $projectRoot) {}

    public function getName(): string
    {
        return 'run_spinx_command';
    }

    public function getDescription(): string
    {
        return 'Run a safe Spinx CLI command (e.g. "make:module Billing --all", "migrate", "schema:compile", "cache:clear"). Only pre-approved framework commands are permitted.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'command' => [
                    'type'        => 'string',
                    'description' => 'The spinx CLI command and arguments (e.g. "make:module Billing --all", "migrate", "schema:compile")',
                ],
            ],
            'required'   => ['command'],
        ];
    }

    public function execute(array $arguments): array
    {
        $commandStr = trim((string) ($arguments['command'] ?? ''));

        if ($commandStr === '') {
            return ['error' => 'Command string cannot be empty.'];
        }

        // Validate command against allowlist
        $parts = preg_split('/\s+/', $commandStr);
        $baseCommand = $parts[0] ?? '';

        if (!in_array($baseCommand, self::ALLOWED_COMMANDS, true)) {
            return [
                'error' => "Command [{$baseCommand}] is not permitted. Allowed commands: " . implode(', ', self::ALLOWED_COMMANDS),
            ];
        }

        // Reject dangerous shell chaining/redirection characters
        if (preg_match('/[;&|`$><\r\n]/', $commandStr)) {
            return ['error' => 'Command chaining, piping, or shell redirection is not allowed.'];
        }

        $escapedArgs = array_map('escapeshellarg', array_slice($parts, 1));
        $cmd = PHP_BINARY . ' ' . escapeshellarg($this->projectRoot . '/spinx') . ' ' . escapeshellcmd($baseCommand) . ($escapedArgs !== [] ? ' ' . implode(' ', $escapedArgs) : '');
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
            'command'  => $commandStr,
            'exitCode' => $exitCode,
            'stdout'   => $stdout,
            'stderr'   => $stderr,
            'success'  => $exitCode === 0,
        ];
    }
}
