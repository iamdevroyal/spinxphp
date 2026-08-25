<?php

declare(strict_types=1);

namespace Spinx\Generator;

final class MiddlewareGenerator extends AbstractGenerator
{
    /** @return array{files: string[], snippet: string} */
    public function generate(string $moduleName, string $name): array
    {
        $moduleDir = $this->assertModuleExists($moduleName);
        $this->assertValidClassName($name);

        $className = str_ends_with($name, 'Middleware') ? $name : $name . 'Middleware';
        $path = $moduleDir . '/Infrastructure/Http/Middleware/' . $className . '.php';

        $this->writeFile($path, $this->renderStub('middleware.php.stub', [
            '{{MODULE}}' => $moduleName,
            '{{CLASS}}' => $className,
        ]));

        $snippet = <<<PHP
            // Attach to a route in app/Modules/{$moduleName}/module.php by
            // adding a '_middleware' default alongside '_controller' — the
            // first class listed runs first (outermost wrapper):
            \$routes->add('some.route', new Route(
                '/some-path',
                defaults: [
                    '_controller' => SomeController::class,
                    '_middleware' => [\\App\\Modules\\{$moduleName}\\Infrastructure\\Http\\Middleware\\{$className}::class],
                ],
                methods: ['GET']
            ));

            // If the middleware has constructor dependencies, register it
            // in 'services' too so the container can autowire them —
            // setPublic(true) is required, same as controllers, since
            // Pipeline resolves middleware via \$container->get() directly:
            \$container->register(\\App\\Modules\\{$moduleName}\\Infrastructure\\Http\\Middleware\\{$className}::class)
                ->setAutowired(true)
                ->setPublic(true);
            PHP;

        return ['files' => [$path], 'snippet' => $snippet];
    }
}
