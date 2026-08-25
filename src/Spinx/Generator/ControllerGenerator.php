<?php

declare(strict_types=1);

namespace Spinx\Generator;

final class ControllerGenerator extends AbstractGenerator
{
    /** @return array{files: string[], snippet: string} */
    public function generate(string $moduleName, string $name): array
    {
        $moduleDir = $this->assertModuleExists($moduleName);
        $this->assertValidClassName($name);

        $className = str_ends_with($name, 'Controller') ? $name : $name . 'Controller';
        $path = $moduleDir . '/Infrastructure/Http/Controllers/' . $className . '.php';

        $this->writeFile($path, $this->renderStub('controller.php.stub', [
            '{{MODULE}}' => $moduleName,
            '{{CLASS}}' => $className,
        ]));

        $base = preg_replace('/Controller$/', '', $className);
        $routeName = strtolower($moduleName) . '.' . self::toSnakeCase($base);
        $alias = self::toSnakeCase($base);

        $snippet = <<<PHP
            // Paste into app/Modules/{$moduleName}/module.php:

            // in 'controllers':
            \$r->registerController('{$alias}', \\App\\Modules\\{$moduleName}\\Infrastructure\\Http\\Controllers\\{$className}::class);

            // in 'routes':
            Route::get(['{$routeName}', '/{$alias}'])->controller('{$alias}');
            PHP;

        return ['files' => [$path], 'snippet' => $snippet];
    }
}
