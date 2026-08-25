<?php

declare(strict_types=1);

namespace Spinx\Generator;

/**
 * Backs `spinx make:module <Name>`. Produces the exact folder layout the
 * kernel's ModuleLoader expects (build spec §5.1) — this is what makes
 * "enforced DDD" a lived developer experience rather than just a rule in
 * a doc: every module you generate looks identical in shape, every time.
 */
final class ModuleGenerator
{
    /** Leaf directories created inside every generated module. */
    private const LAYER_DIRS = [
        'Domain/Entities',
        'Domain/ValueObjects',
        'Domain/Events',
        'Domain/Repositories',
        'Application/Services',
        'Application/Commands',
        'Application/Queries',
        'Application/Mail',
        'Application/Jobs',
        'Infrastructure/Repositories',
        'Infrastructure/Http/Controllers',
        'Infrastructure/Http/Middleware',
        'Infrastructure/Http/Views',
        'Infrastructure/Http/Views/mail',
        'Infrastructure/Persistence/Migrations',
        'Infrastructure/Persistence/Models',
    ];

    public function __construct(
        private readonly string $projectRoot,
    ) {
    }

    /**
     * @return string[] Absolute paths of every file/directory created
     *
     * @throws \InvalidArgumentException If the module name is invalid
     * @throws \RuntimeException If the module already exists
     */
    public function generate(string $moduleName): array
    {
        $this->assertValidName($moduleName);

        $moduleDir = $this->projectRoot . '/app/Modules/' . $moduleName;

        if (is_dir($moduleDir)) {
            throw new \RuntimeException(sprintf(
                'Module "%s" already exists at %s.',
                $moduleName,
                $moduleDir
            ));
        }

        $created = [];

        foreach (self::LAYER_DIRS as $relativeDir) {
            $fullDir = $moduleDir . '/' . $relativeDir;
            $this->mkdirRecursive($fullDir);

            // Git doesn't track empty directories — drop a .gitkeep in
            // every leaf so the enforced skeleton actually shows up when
            // the module is committed, even before the developer has
            // added any classes to a given layer.
            $gitkeep = $fullDir . '/.gitkeep';
            file_put_contents($gitkeep, '');
            $created[] = $gitkeep;
        }

        $moduleFile = $moduleDir . '/module.php';
        file_put_contents($moduleFile, $this->renderStub('module.php.stub', $moduleName));
        $created[] = $moduleFile;

        $readmeFile = $moduleDir . '/README.md';
        file_put_contents($readmeFile, $this->renderStub('README.md.stub', $moduleName));
        $created[] = $readmeFile;

        $this->registerInSpinxJson($moduleName);

        return $created;
    }

    private function assertValidName(string $moduleName): void
    {
        // StudlyCase, single word — e.g. "Orders", "Billing", "Health".
        // Matches the namespace segment App\Modules\<Name>\... exactly,
        // so there's no risk of a name that's valid on disk but invalid
        // as a PHP namespace component.
        if (!preg_match('/^[A-Z][A-Za-z0-9]*$/', $moduleName)) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid module name "%s". Use StudlyCase, e.g. "Orders" or "BillingAccounts".',
                $moduleName
            ));
        }
    }

    private function mkdirRecursive(string $dir): void
    {
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException("Failed to create directory: {$dir}");
        }
    }

    private function renderStub(string $stubName, string $moduleName): string
    {
        $stubPath = __DIR__ . '/stubs/' . $stubName;
        $contents = file_get_contents($stubPath);

        if ($contents === false) {
            throw new \RuntimeException("Missing generator stub: {$stubPath}");
        }

        return str_replace(
            ['{{MODULE}}', '{{MODULE_SNAKE}}'],
            [$moduleName, $this->toSnakeCase($moduleName)],
            $contents
        );
    }

    private function toSnakeCase(string $studlyCase): string
    {
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $studlyCase));
    }

    /**
     * Adds the new module to spinx.json's registry, defaulting to enabled.
     * Existing entries and unrelated config keys are preserved as-is.
     */
    private function registerInSpinxJson(string $moduleName): void
    {
        $configFile = $this->projectRoot . '/spinx.json';

        if (!is_file($configFile)) {
            return; // No registry to update — module still works, discovery defaults to enabled.
        }

        $config = json_decode((string) file_get_contents($configFile), true) ?? [];
        $config['modules'] ??= [];
        $config['modules'][$moduleName] = true;

        file_put_contents(
            $configFile,
            json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
        );
    }
}
