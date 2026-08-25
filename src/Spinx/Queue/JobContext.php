<?php

declare(strict_types=1);

namespace Spinx\Queue;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A Job is serialize()'d when dispatched (see QueueManager::dispatch())
 * and unserialize()'d fresh by the worker process later — it can't hold
 * a live service (like Mailer, whose Symfony transport isn't meaningful
 * across that boundary) as a constructor-injected property the way a
 * controller can. This gives a deserialized Job a way to resolve real
 * services inside handle() instead. Same static-resolver pattern as
 * Spinx\Database\Model::setConnectionManager() — framework-level state,
 * set once by `spinx queue:work` at worker boot, not app/Modules
 * business-logic state.
 */
final class JobContext
{
    private static ?ContainerInterface $container = null;

    public static function setContainer(ContainerInterface $container): void
    {
        self::$container = $container;
    }

    public static function resolve(string $serviceId): object
    {
        if (self::$container === null) {
            throw new \RuntimeException(
                'JobContext::setContainer() must be called before a Job can resolve services — ' .
                'this happens automatically inside `spinx queue:work`.'
            );
        }

        return self::$container->get($serviceId);
    }
}
