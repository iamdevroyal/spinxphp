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
        $routePath = self::toSnakeCase($base);

        $snippet = <<<PHP
            // Not auto-wired — module.php is real PHP, not a data file, so
            // editing it programmatically risks corrupting a hand-tuned route
            // table. Paste this into app/Modules/{$moduleName}/module.php yourself:

            // in 'routes':
            \$routes->add('{$routeName}', new Route(
                '/{$routePath}',
                defaults: ['_controller' => \\App\\Modules\\{$moduleName}\\Infrastructure\\Http\\Controllers\\{$className}::class],
                methods: ['GET']
            ));

            // in 'services':
            \$container->register(\\App\\Modules\\{$moduleName}\\Infrastructure\\Http\\Controllers\\{$className}::class)
                ->setAutowired(true)
                ->setPublic(true);
            PHP;

        return ['files' => [$path], 'snippet' => $snippet];
    }
}
