<?php

declare(strict_types=1);

namespace Spinx\Container;

use Symfony\Component\Config\ConfigCache;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\Config\FileLocator;

/**
 * Compiles the DI container exactly once per process boot, then caches the
 * compiled class to disk. On subsequent boots (new worker spawn, process
 * restart) the cached class is loaded directly with zero reflection cost —
 * this is the single biggest contributor to Spinx's cold-start speed
 * advantage over a traditional per-request PHP-FPM bootstrap.
 */
final class ContainerFactory
{
    public function __construct(
        private readonly string $projectRoot,
        private readonly bool $debug = false,
    ) {
    }

    public function build(): \Symfony\Component\DependencyInjection\ContainerInterface
    {
        $cacheFile = $this->projectRoot . '/storage/cache/container.php';
        $cache = new ConfigCache($cacheFile, $this->debug);

        if (!$cache->isFresh()) {
            $containerBuilder = new ContainerBuilder();
            $containerBuilder->setParameter('spinx.project_root', $this->projectRoot);
            $containerBuilder->setParameter('spinx.cache_dir', $this->projectRoot . '/storage/cache');

            // Base framework services.
            $loader = new PhpFileLoader($containerBuilder, new FileLocator($this->projectRoot . '/config'));
            // Loads config/container.php — DI wiring only, not to be
            // confused with config/services.php (third-party API
            // credentials, plain array, read via the config() helper —
            // see container.php's own docblock for why these are two
            // separate files despite the similar old name).
            $loader->load('container.php');

            // Each enabled module contributes its own DI bindings via
            // module.php — see Spinx\Routing\ModuleLoader, which this
            // factory delegates to so container compilation and route
            // compilation stay in lockstep (both happen once, at boot).
            (new \Spinx\Routing\ModuleLoader($this->projectRoot))->registerServices($containerBuilder);

            // Decides request-scoped vs singleton for every module service
            // tagged above, and writes the result to a container parameter
            // Kernel::boot() reads to build the RequestScope wrapper.
            // See build spec §4 and RequestScopePass's own docblock.
            $containerBuilder->addCompilerPass(new \Spinx\Container\Compiler\RequestScopePass());

            $containerBuilder->compile();

            $dumper = new PhpDumper($containerBuilder);
            $cache->write(
                $dumper->dump(['class' => 'CachedSpinxContainer']),
                $containerBuilder->getResources()
            );
        }

        require_once $cacheFile;

        /** @psalm-suppress UndefinedClass */
        return new \CachedSpinxContainer();
    }
}
