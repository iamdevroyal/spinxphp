<?php

declare(strict_types=1);

namespace Spinx\Routing;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Routing\RouteCollection;

/**
 * This is the enforcement mechanism behind Spinx's DDD architecture.
 *
 * There is deliberately no code path anywhere in the kernel that scans a
 * bare app/Controllers directory, a routes/web.php file, or any other
 * "convenience" location. The ONLY way a route or service gets registered
 * is by living inside app/Modules/<Name>/module.php, which is itself only
 * ever produced (in its expected shape) by `spinx make:module`.
 */
final class ModuleLoader
{
    /** @var string[]|null */
    private ?array $moduleDirsCache = null;

    /** Shared registry populated across all modules at boot. */
    private readonly AliasRegistry $aliasRegistry;

    private bool $aliasesPopulated = false;

    public function __construct(
        private readonly string $projectRoot,
    ) {
        $this->aliasRegistry = new AliasRegistry();
    }

    /**
     * Every enabled module contributes its DI bindings here. Called once
     * during container compilation (see ContainerFactory::build()).
     */
    public function registerServices(ContainerBuilder $container): void
    {
        if (is_file($this->projectRoot . '/spinx.json')) {
            $container->addResource(new \Symfony\Component\Config\Resource\FileResource($this->projectRoot . '/spinx.json'));
        }

        // Run 'services' closures first for non-alias DI bindings (e.g. interfaces, repositories).
        foreach ($this->discoverModules() as $moduleDir) {
            if (is_file($moduleDir . '/module.php')) {
                $container->addResource(new \Symfony\Component\Config\Resource\FileResource($moduleDir . '/module.php'));
            }

            $definition = $this->loadModuleDefinition($moduleDir);

            if (isset($definition['services']) && is_callable($definition['services'])) {
                $before = array_keys($container->getDefinitions());
                $definition['services']($container, $moduleDir);
                $after = array_keys($container->getDefinitions());

                foreach (array_diff($after, $before) as $newlyRegisteredId) {
                    $container->getDefinition($newlyRegisteredId)->addTag('spinx.module_service');
                }
            }
        }

        $this->ensureAliasesPopulated();

        // Auto-register all aliased controllers and middlewares into the container.
        $this->aliasRegistry->registerServicesInContainer($container);

        // Tag all alias-registered services too so RequestScopePass handles them.
        foreach ($this->aliasRegistry->allControllers() as $class) {
            $baseClass = str_contains($class, '@') ? explode('@', $class, 2)[0] : $class;
            if ($container->has($baseClass)) {
                $container->getDefinition($baseClass)->addTag('spinx.module_service');
            }
        }

        foreach ($this->aliasRegistry->allMiddlewares() as $class) {
            $baseClass = str_contains($class, '@') ? explode('@', $class, 2)[0] : $class;
            if ($container->has($baseClass)) {
                $container->getDefinition($baseClass)->addTag('spinx.module_service');
            }
        }
    }

    /**
     * Every enabled module contributes its routes here. Called once during
     * route compilation, cached alongside the container.
     */
    public function loadRoutes(RouteCollection $routes): void
    {
        $this->ensureAliasesPopulated();

        foreach ($this->discoverModules() as $moduleDir) {
            $definition = $this->loadModuleDefinition($moduleDir);

            if (!isset($definition['routes']) || !is_callable($definition['routes'])) {
                continue;
            }

            // Create a fresh RouteBuilder for this module, backed by the
            // shared AliasRegistry (already populated by ensureAliasesPopulated()).
            $builder = new RouteBuilder('', $this->aliasRegistry);

            // Set the active builder so Route::get() etc. know where to deposit definitions.
            Route::setActiveBuilder($builder);

            try {
                $definition['routes']($builder);
            } finally {
                Route::clearActiveBuilder();
            }

            // Compile all collected definitions into real Symfony Route objects.
            $builder->compileInto($routes);
        }
    }

    private function ensureAliasesPopulated(): void
    {
        if ($this->aliasesPopulated) {
            return;
        }

        $this->aliasesPopulated = true;

        foreach ($this->discoverModules() as $moduleDir) {
            $definition = $this->loadModuleDefinition($moduleDir);

            if (isset($definition['controllers']) && is_callable($definition['controllers'])) {
                $definition['controllers']($this->aliasRegistry);
            }

            if (isset($definition['middlewares']) && is_callable($definition['middlewares'])) {
                $definition['middlewares']($this->aliasRegistry);
            }
        }
    }

    /**
     * @return string[] Absolute paths to each enabled module's root directory
     */
    private function discoverModules(): array
    {
        if ($this->moduleDirsCache !== null) {
            return $this->moduleDirsCache;
        }

        $modulesRoot = $this->projectRoot . '/app/Modules';
        $enabled = $this->readEnabledModulesFromRegistry();

        $dirs = [];
        if (is_dir($modulesRoot)) {
            foreach (scandir($modulesRoot) ?: [] as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }

                $moduleDir = $modulesRoot . '/' . $entry;
                $moduleFile = $moduleDir . '/module.php';

                if (!is_dir($moduleDir) || !is_file($moduleFile)) {
                    continue;
                }

                if ($enabled !== null && isset($enabled[$entry]) && $enabled[$entry] === false) {
                    continue;
                }

                $dirs[] = $moduleDir;
            }
        }

        return $this->moduleDirsCache = $dirs;
    }

    /**
     * @return array{routes?: callable, services?: callable, controllers?: callable, middlewares?: callable}
     */
    private function loadModuleDefinition(string $moduleDir): array
    {
        $definition = require $moduleDir . '/module.php';

        if (!is_array($definition)) {
            throw new \RuntimeException(sprintf(
                'Module definition at %s/module.php must return an array.',
                $moduleDir
            ));
        }

        return $definition;
    }

    /**
     * @return array<string, bool>|null Null if spinx.json has no explicit "modules" key
     */
    private function readEnabledModulesFromRegistry(): ?array
    {
        $configFile = $this->projectRoot . '/spinx.json';

        if (!is_file($configFile)) {
            return null;
        }

        $config = json_decode((string) file_get_contents($configFile), true);

        return $config['modules'] ?? null;
    }
}
