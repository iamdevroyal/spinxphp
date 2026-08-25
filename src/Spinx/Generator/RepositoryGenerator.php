<?php

declare(strict_types=1);

namespace Spinx\Generator;

/**
 * Generates the pair together deliberately — a repository interface
 * without an implementation (or vice versa) isn't a usable pattern, and
 * generating both from one command keeps the Domain/Infrastructure split
 * from build spec §5.2 the path of least resistance rather than something
 * a developer has to remember to do manually.
 */
final class RepositoryGenerator extends AbstractGenerator
{
    /** @return array{files: string[], snippet: string} */
    public function generate(string $moduleName, string $name): array
    {
        $moduleDir = $this->assertModuleExists($moduleName);
        $this->assertValidClassName($name);

        // Normalize whether the caller passed "Order", "OrderRepository",
        // or "OrderRepositoryInterface" — all produce the same pair.
        $base = (string) preg_replace('/Repository(Interface)?$/', '', $name);

        if ($base === '') {
            throw new \InvalidArgumentException(sprintf(
                'Name "%s" has nothing left after stripping the Repository/RepositoryInterface suffix — pass the entity name, e.g. "Order".',
                $name
            ));
        }

        $interfaceName = $base . 'RepositoryInterface';
        $implName = $base . 'Repository';

        $interfacePath = $moduleDir . '/Domain/Repositories/' . $interfaceName . '.php';
        $implPath = $moduleDir . '/Infrastructure/Repositories/' . $implName . '.php';

        $this->writeFile($interfacePath, $this->renderStub('repository-interface.php.stub', [
            '{{MODULE}}' => $moduleName,
            '{{INTERFACE}}' => $interfaceName,
            '{{BASE}}' => $base,
        ]));

        $this->writeFile($implPath, $this->renderStub('repository.php.stub', [
            '{{MODULE}}' => $moduleName,
            '{{INTERFACE}}' => $interfaceName,
            '{{CLASS}}' => $implName,
            '{{BASE}}' => $base,
        ]));

        $snippet = <<<PHP
            // Add to app/Modules/{$moduleName}/module.php, in 'services'
            // — binds the interface to its implementation, so anything
            // that type-hints {$interfaceName} gets {$implName} injected:
            \$container->setAlias(
                \\App\\Modules\\{$moduleName}\\Domain\\Repositories\\{$interfaceName}::class,
                \\App\\Modules\\{$moduleName}\\Infrastructure\\Repositories\\{$implName}::class
            );
            \$container->register(\\App\\Modules\\{$moduleName}\\Infrastructure\\Repositories\\{$implName}::class)
                ->setAutowired(true);
            PHP;

        return ['files' => [$interfacePath, $implPath], 'snippet' => $snippet];
    }
}
