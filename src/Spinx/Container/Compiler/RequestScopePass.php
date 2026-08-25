<?php

declare(strict_types=1);

namespace Spinx\Container\Compiler;

use Spinx\Container\Attribute\Singleton;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Runs once, at container compile time (process boot). Every service
 * tagged "spinx.module_service" — which ModuleLoader applies automatically
 * to anything a module registers, see ModuleLoader::registerServices() —
 * is classified as request-scoped unless its class carries the explicit
 * #[Singleton] attribute.
 *
 * The result is written to the "spinx.request_scoped_service_ids"
 * container parameter, which Kernel::boot() reads to construct the
 * RequestScope wrapper. This is the mechanism that turns "request-scoped
 * by default" from a stated policy into an enforced one — a developer
 * would have to add #[Singleton] AND be wrong about it to leak state.
 */
final class RequestScopePass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $requestScopedIds = [];

        foreach (array_keys($container->findTaggedServiceIds('spinx.module_service')) as $id) {
            $definition = $container->getDefinition($id);
            $class = $definition->getClass() ?? $id;

            if ($this->isExplicitlySingleton($class)) {
                continue;
            }

            $requestScopedIds[] = $id;
        }

        $container->setParameter('spinx.request_scoped_service_ids', $requestScopedIds);
    }

    private function isExplicitlySingleton(string $class): bool
    {
        if (!class_exists($class) && !interface_exists($class)) {
            // Class not autoloadable at compile time (e.g. a closure-based
            // service with no class string) — treat conservatively as
            // request-scoped rather than risk an unintended singleton.
            return false;
        }

        $reflection = new \ReflectionClass($class);

        return count($reflection->getAttributes(Singleton::class)) > 0;
    }
}
