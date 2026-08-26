<?php

declare(strict_types=1);

namespace Spinx\Ai\Tools;

/**
 * Analyzes PHP files for syntax validity and basic DDD compliance.
 */
final class CodeAnalyzerTool implements ToolInterface
{
    public function __construct(private readonly string $projectRoot) {}

    public function getName(): string
    {
        return 'analyze_code';
    }

    public function getDescription(): string
    {
        return 'Lint PHP syntax and check Spinx DDD compliance for a given file. Returns validation results and suggestions.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'path' => [
                    'type'        => 'string',
                    'description' => 'Relative path from project root to the PHP file to analyze',
                ],
            ],
            'required'   => ['path'],
        ];
    }

    public function execute(array $arguments): array
    {
        $relPath  = ltrim($arguments['path'], '/\\');
        $fullPath = $this->projectRoot . '/' . $relPath;

        if (!is_file($fullPath)) {
            return ['error' => "File not found: {$relPath}"];
        }

        // PHP syntax lint
        $output     = [];
        $returnCode = 0;
        exec("php -l " . escapeshellarg($fullPath) . " 2>&1", $output, $returnCode);
        $syntaxValid   = ($returnCode === 0);
        $syntaxMessage = implode("\n", $output);

        // Read content for DDD compliance checks
        $content    = file_get_contents($fullPath);
        $warnings   = [];
        $suggestions = [];

        // Detect Controllers referencing raw Symfony classes
        if (str_contains($relPath, 'Controller') || str_contains($relPath, 'Controllers')) {
            if (preg_match('/use Symfony\\\\Component\\\\HttpFoundation\\\\(Request|Response|JsonResponse)/', $content)) {
                $warnings[]    = 'Controller imports raw Symfony HTTP classes directly.';
                $suggestions[] = 'Use Spinx facades: use Spinx\\Http\\Request, Spinx\\Http\\Response, Spinx\\Http\\JsonResponse';
            }
            if (preg_match('/use Spinx\\\\Validation\\\\Validator;/', $content)) {
                $suggestions[] = 'Prefer using the Validate facade or Request::validate() over injecting Validator directly.';
            }
        }

        // Detect Domain entities importing infrastructure
        if (str_contains($relPath, 'Domain/Entities') || str_contains($relPath, 'Domain\\Entities')) {
            if (preg_match('/use Spinx\\\\(Database|Cache|Session|Http)/', $content)) {
                $warnings[] = 'Domain Entity imports infrastructure/framework concerns — violates DDD isolation.';
            }
        }

        // Detect Repository implementations not implementing a contract
        if (str_contains($relPath, 'Infrastructure/Persistence') && str_contains($relPath, 'Repository')) {
            if (!preg_match('/implements\s+\w+RepositoryInterface/', $content)) {
                $warnings[]    = 'Repository class does not implement a RepositoryInterface contract.';
                $suggestions[] = 'Create a Domain/Repositories/{Name}RepositoryInterface and implement it here.';
            }
        }

        return [
            'path'        => $relPath,
            'valid'       => $syntaxValid && empty($warnings),
            'syntax'      => [
                'valid'   => $syntaxValid,
                'message' => $syntaxMessage,
            ],
            'warnings'    => $warnings,
            'suggestions' => $suggestions,
        ];
    }
}
