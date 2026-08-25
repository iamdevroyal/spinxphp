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

        $base = preg_replace('/Middleware$/', '', $className);
        $alias = self::toSnakeCase($base);

        $snippet = <<<PHP
            // Paste into app/Modules/{$moduleName}/module.php:

            // in 'middlewares':
            \$r->registerMiddleware('{$alias}', \\App\\Modules\\{$moduleName}\\Infrastructure\\Http\\Middleware\\{$className}::class);

            // in 'routes':
            Route::get(['some.route', '/some-path'])
                ->middleware(['{$alias}'])
                ->controller('some_controller');
            PHP;

        return ['files' => [$path], 'snippet' => $snippet];
    }
}
