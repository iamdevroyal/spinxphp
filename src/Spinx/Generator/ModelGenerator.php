<?php

declare(strict_types=1);

namespace Spinx\Generator;

final class ModelGenerator extends AbstractGenerator
{
    /** @return array{files: string[], snippet: string} */
    public function generate(string $moduleName, string $name): array
    {
        $moduleDir = $this->assertModuleExists($moduleName);
        $this->assertValidClassName($name);

        $path = $moduleDir . '/Infrastructure/Persistence/Models/' . $name . '.php';

        $this->writeFile($path, $this->renderStub('model.php.stub', [
            '{{MODULE}}' => $moduleName,
            '{{CLASS}}' => $name,
            '{{TABLE}}' => self::toSnakeCase($name) . 's',
        ]));

        return ['files' => [$path], 'snippet' => ''];
    }
}
