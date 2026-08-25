<?php

declare(strict_types=1);

namespace Spinx\Generator;

/**
 * Common logic every layer-scoped generator (controller, entity, service,
 * repository, model, migration) needs: confirming the target module
 * actually exists before scaffolding into it, validating StudlyCase
 * names, refusing to overwrite existing files, and rendering stub
 * templates with placeholder substitution.
 */
abstract class AbstractGenerator
{
    public function __construct(
        protected readonly string $projectRoot,
    ) {
    }

    /** @return string Absolute path to the module's root directory */
    protected function assertModuleExists(string $moduleName): string
    {
        $this->assertValidClassName($moduleName);
        $moduleDir = $this->projectRoot . '/app/Modules/' . $moduleName;

        if (!is_dir($moduleDir)) {
            throw new \RuntimeException(sprintf(
                'Module "%s" does not exist. Run `spinx make:module %1$s` first.',
                $moduleName
            ));
        }

        return $moduleDir;
    }

    protected function assertValidClassName(string $name): void
    {
        if (!preg_match('/^[A-Z][A-Za-z0-9]*$/', $name)) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid name "%s". Use StudlyCase, e.g. "Order" or "CreateOrder".',
                $name
            ));
        }
    }

    protected function writeFile(string $path, string $content): void
    {
        if (is_file($path)) {
            throw new \RuntimeException("File already exists: {$path}");
        }

        $dir = dirname($path);

        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException("Failed to create directory: {$dir}");
        }

        file_put_contents($path, $content);
    }

    /** @param array<string, string> $replacements */
    protected function renderStub(string $stubFile, array $replacements): string
    {
        $stubPath = __DIR__ . '/stubs/' . $stubFile;
        $contents = file_get_contents($stubPath);

        if ($contents === false) {
            throw new \RuntimeException("Missing generator stub: {$stubPath}");
        }

        return strtr($contents, $replacements);
    }

    protected static function toSnakeCase(string $studlyCase): string
    {
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $studlyCase));
    }
}
