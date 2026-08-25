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
 *
 * Bypassing the DDD structure isn't just discouraged — it's structurally
 * unavailable, matching the framework's spec's requirement that enforcement be
 * architectural, not a linting convention.
 *
 * --- module.php shape (v2 — fluent DSL) ---
 *
 * return [
 *     // Optional: register controller aliases for use in Route::*()->controller().
 *     // DI registration is automatic — no separate 'services' block needed for these.
 *     'controllers' => static function (AliasRegistry $r): void {
 *         $r->registerController('order_list', OrderListController::class);
 *     },
 *
 *     // Optional: register middleware aliases for use in Route::*()->middleware([]).
 *     'middlewares' => static function (AliasRegistry $r): void {
 *         $r->registerMiddleware('auth',       AuthMiddleware::class);
 *         $r->registerMiddleware('rate_limit', RateLimitMiddleware::class);
 *     },
 *
 *     // Required: declare routes using the fluent Route:: DSL.
 *     'routes' => static function (RouteBuilder $routes): void {
 *         Route::get(['orders.index', '/orders'])->controller('order_list');
 *     },
 *
 *     // Optional: non-controller/non-middleware DI bindings.
 *     'services' => static function (ContainerBuilder $container, string $moduleDir): void {
 *         $container->register(OrderRepository::class)->setAutowired(true)->setPublic(true);
 *     },
 * ];
 */
final class ModuleLoader
{
    /** @var string[]|null */
    private ?array $moduleDirsCache = null;

    /** Shared registry populated across all modules at boot. */
    private readonly AliasRegistry $aliasRegistry;

    public function __construct(
        private readonly string $projectRoot,
    ) {
        $this->aliasRegistry = new AliasRegistry();
    }

    /**
     * Every enabled module contributes its DI bindings here. Called once
     * during container compilation (see ContainerFactory::build()).
     *
     * Processes 'controllers', 'middlewares', and 'services' closures —
     * in that order — so aliases are available for DI auto-registration
     * before any other service definitions run.
     *
     * Any service a module registers here is automatically tagged
     * "spinx.module_service" by diffing the container's definitions
     * before/after the module's closure runs. Module authors never tag
     * anything by hand — RequestScopePass (see
     * Spinx\Container\Compiler\RequestScopePass) later reads this tag to
     * decide request-scoped vs singleton, so the state-safety enforcement
     * from build spec §4 applies to every module service without opt-in.
     */
    public function registerServices(ContainerBuilder $container): void
    {
        foreach ($this->discoverModules() as $moduleDir) {
            $definition = $this->loadModuleDefinition($moduleDir);

            // 1. Run 'controllers' closure — fills AliasRegistry with controller aliases.
            if (isset($definition['controllers']) && is_callable($definition['controllers'])) {
                $definition['controllers']($this->aliasRegistry);
            }

            // 2. Run 'middlewares' closure — fills AliasRegistry with middleware aliases.
            if (isset($definition['middlewares']) && is_callable($definition['middlewares'])) {
                $definition['middlewares']($this->aliasRegistry);
            }
        }

        // Auto-register all aliased controllers and middlewares into the container.
        $this->aliasRegistry->registerServicesInContainer($container);

        // 3. Run 'services' closures for non-alias DI bindings.
        foreach ($this->discoverModules() as $moduleDir) {
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

        // Tag all alias-registered services too so RequestScopePass handles them.
        foreach ($this->aliasRegistry->allControllers() as $class) {
            if ($container->has($class)) {
                $container->getDefinition($class)->addTag('spinx.module_service');
            }
        }

        foreach ($this->aliasRegistry->allMiddlewares() as $class) {
            if ($container->has($class)) {
                $container->getDefinition($class)->addTag('spinx.module_service');
            }
        }
    }

    /**
     * Every enabled module contributes its routes here. Called once during
     * route compilation, cached alongside the container.
     */
    public function loadRoutes(RouteCollection $routes): void
    {
        foreach ($this->discoverModules() as $moduleDir) {
            $definition = $this->loadModuleDefinition($moduleDir);

            if (!isset($definition['routes']) || !is_callable($definition['routes'])) {
                continue;
            }

            // Create a fresh RouteBuilder for this module, backed by the
            // shared AliasRegistry (already populated by registerServices()).
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

                // If spinx.json declares an explicit module registry, honor
                // enable/disable toggles. Absence of a registry entry defaults
                // to enabled — a module only needs a module.php to be discoverable.
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
                'Module definition at %s/module.php must return an array. ' .
                'Expected keys: "routes" (required), "controllers", "middlewares", "services" (all optional callables).',
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
