<?php

declare(strict_types=1);

namespace Spinx\Generator;

final class ServiceGenerator extends AbstractGenerator
{
    /** @return array{files: string[], snippet: string} */
    public function generate(string $moduleName, string $name): array
    {
        $moduleDir = $this->assertModuleExists($moduleName);
        $this->assertValidClassName($name);

        $className = str_ends_with($name, 'Service') ? $name : $name . 'Service';
        $path = $moduleDir . '/Application/Services/' . $className . '.php';

        $this->writeFile($path, $this->renderStub('service.php.stub', [
            '{{MODULE}}' => $moduleName,
            '{{CLASS}}' => $className,
        ]));

        $snippet = <<<PHP
            // Add to app/Modules/{$moduleName}/module.php, in 'services'
            // (only needed if a controller doesn't already autowire this —
            // constructor-typed dependencies resolve automatically):
            \$container->register(\\App\\Modules\\{$moduleName}\\Application\\Services\\{$className}::class)
                ->setAutowired(true);
            PHP;

        return ['files' => [$path], 'snippet' => $snippet];
    }
}
