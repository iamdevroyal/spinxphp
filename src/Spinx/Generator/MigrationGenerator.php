<?php

declare(strict_types=1);

namespace Spinx\Generator;

final class MigrationGenerator extends AbstractGenerator
{
    /** @return array{files: string[], snippet: string} */
    public function generate(string $moduleName, string $description): array
    {
        $moduleDir = $this->assertModuleExists($moduleName);

        if (!preg_match('/^[a-z][a-z0-9_]*$/', $description)) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid migration description "%s". Use snake_case, e.g. "create_orders_table".',
                $description
            ));
        }

        $timestamp = (new \DateTimeImmutable())->format('Y_m_d_His');
        $filename = "{$timestamp}_{$description}.php";
        $path = $moduleDir . '/Infrastructure/Persistence/Migrations/' . $filename;

        $this->writeFile($path, $this->renderStub('migration.php.stub', [
            '{{MODULE}}' => $moduleName,
        ]));

        return ['files' => [$path], 'snippet' => ''];
    }
}
