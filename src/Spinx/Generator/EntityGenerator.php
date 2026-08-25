<?php

declare(strict_types=1);

namespace Spinx\Generator;

final class EntityGenerator extends AbstractGenerator
{
    /** @return array{files: string[], snippet: string} */
    public function generate(string $moduleName, string $name): array
    {
        $moduleDir = $this->assertModuleExists($moduleName);
        $this->assertValidClassName($name);

        $path = $moduleDir . '/Domain/Entities/' . $name . '.php';

        $this->writeFile($path, $this->renderStub('entity.php.stub', [
            '{{MODULE}}' => $moduleName,
            '{{CLASS}}' => $name,
        ]));

        return ['files' => [$path], 'snippet' => ''];
    }
}
