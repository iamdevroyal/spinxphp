<?php

declare(strict_types=1);

namespace Spinx\Kernel;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Guards against the single biggest correctness risk of a persistent-process
 * runtime: state leaking between requests through static properties or
 * singleton-scoped services.
 *
 * Every service tagged "request-scoped" (the default for anything generated
 * via `spinx make:*`) is torn down and rebuilt fresh at the start of each
 * request cycle. Only services explicitly marked "singleton" in module.php
 * survive across requests within the same worker/coroutine.
 */
final class RequestScope
{
    /** @var array<string, object> */
    private array $instances = [];

    public function __construct(
        private readonly ContainerInterface $rootContainer,
        /** @var string[] Service IDs tagged as request-scoped in the compiled container */
        private readonly array $requestScopedServiceIds = [],
    ) {
    }

    /**
     * Reset all request-scoped instances. Must be called by the active
     * ServerAdapter at the very start of handle(), before any application
     * code runs, so a previous request's state can never bleed into the
     * next one on the same worker/coroutine.
     */
    public function reset(): void
    {
        $this->instances = [];
    }

    public function get(string $serviceId): object
    {
        if (!in_array($serviceId, $this->requestScopedServiceIds, true)) {
            // Singleton services resolve straight through the root container
            // and are intentionally allowed to persist across requests.
            return $this->rootContainer->get($serviceId);
        }

        return $this->instances[$serviceId] ??= $this->rootContainer->get($serviceId);
    }
}
