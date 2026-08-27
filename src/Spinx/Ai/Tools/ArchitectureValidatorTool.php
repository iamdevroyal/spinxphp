<?php

declare(strict_types=1);

namespace Spinx\Ai\Tools;

/**
 * Validates individual PHP code snippets or file paths against Spinx architectural invariants.
 */
final class ArchitectureValidatorTool implements ToolInterface
{
    public function getName(): string
    {
        return 'validate_architecture';
    }

    public function getDescription(): string
    {
        return 'Validates a PHP code snippet or module file path against Spinx strict Domain-Driven Design (DDD) rules, facade conventions, and persistent runtime safety.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'required'   => ['code'],
            'properties' => [
                'code' => [
                    'type'        => 'string',
                    'description' => 'The PHP code content to validate.',
                ],
                'filePath' => [
                    'type'        => 'string',
                    'description' => 'Optional relative target file path (e.g. "app/Modules/Billing/Domain/Entities/Invoice.php").',
                ],
            ],
        ];
    }

    public function execute(array $arguments): array
    {
        $code = (string) ($arguments['code'] ?? '');
        $filePath = (string) ($arguments['filePath'] ?? '');

        if (trim($code) === '') {
            return ['valid' => false, 'errors' => ['Code content cannot be empty.']];
        }

        $errors = [];
        $warnings = [];

        // 1. Strict types header
        if (!str_contains($code, 'declare(strict_types=1);')) {
            $warnings[] = 'Missing "declare(strict_types=1);" header declaration.';
        }

        // 2. Persistent runtime safety: Superglobals
        if (preg_match('/(\$_SESSION|\$_GET|\$_POST|\$_REQUEST|\$_FILES)/', $code, $matches)) {
            $errors[] = "Prohibited superglobal [{$matches[1]}] detected. In persistent runtimes (RoadRunner/Swoole), superglobals cause cross-request state corruption. Use Spinx\\Http\\Request or Spinx\\Session\\SessionInterface.";
        }

        // 3. Prohibited Laravel packages
        if (preg_match('/use Illuminate\\\\/', $code)) {
            $errors[] = 'Prohibited Laravel "Illuminate\\*" import detected. Use native Spinx facades (Request, Response, DB, Model, Queue, Broadcast, Storage, Vector, Llm).';
        }

        // 4. Domain Layer Purity
        if ($filePath !== '' && (str_contains($filePath, '/Domain/') || str_contains($filePath, '\\Domain\\'))) {
            if (preg_match('/use Spinx\\\\(Database|Cache|Session|Http|Routing|Auth|Queue|Broadcasting)/', $code) ||
                str_contains($code, 'Symfony\\') ||
                str_contains($code, 'Doctrine\\')
            ) {
                $errors[] = 'Domain layer violation: Domain Entities and Repository interfaces must remain pure PHP without framework, DBAL, or HTTP imports.';
            }
        }

        // 5. Controller conventions
        if (str_contains($filePath, 'Controller.php') || str_contains($code, 'class ') && str_contains($code, 'Controller')) {
            if (str_contains($code, 'use Symfony\\Component\\HttpFoundation\\Response') ||
                str_contains($code, 'use Symfony\\Component\\HttpFoundation\\Request')
            ) {
                $warnings[] = 'Controller should use "Spinx\\Http\\Request" and "Spinx\\Http\\Response" rather than raw Symfony HttpFoundation classes.';
            }
        }

        // 6. Path validation (must be in app/Modules/)
        if ($filePath !== '' && !str_starts_with($filePath, 'app/Modules/') && !str_starts_with($filePath, 'config/') && !str_starts_with($filePath, 'resources/')) {
            $errors[] = "Invalid file location [{$filePath}]. In Spinx DDD, all domain logic, controllers, models, and migrations must live in app/Modules/<ModuleName>/, not root folders.";
        }

        $isValid = empty($errors);

        return [
            'valid'    => $isValid,
            'errors'   => $errors,
            'warnings' => $warnings,
            'status'   => $isValid ? '✔ Code strictly complies with Spinx architectural invariants.' : '❌ Architectural violations detected.',
        ];
    }
}
