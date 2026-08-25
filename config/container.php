<?php

declare(strict_types=1);

use Spinx\Database\Connection\{ConnectionManager, ConnectionManagerFactory};
use Spinx\Database\{Migrator, Seeder};
use Spinx\Database\Schema\SchemaBuilder;
use Spinx\Templating\DirectiveCompiler;
use Spinx\Templating\TemplateCache;
use Spinx\Templating\TemplateRenderer;
use Spinx\Templating\Vite;
use Spinx\Templating\ViewFinder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * FRAMEWORK-LEVEL DI wiring only — Symfony's ContainerConfigurator,
 * registering Spinx's own internal services (templating, database
 * connection factory, etc.). This file should stay thin and rarely
 * change as an app grows.
 *
 * This is NOT where application-level configuration (API keys, mail
 * settings, database credentials) lives — that's config/services.php,
 * config/database.php, config/mail.php, and so on, each a plain array
 * read via the config() helper (see Spinx\Support\Config).
 *
 * Application/domain services for a specific module belong in .
 * module's own module.php, not here either.
 */
return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services()
        ->defaults()
        ->autowire()
        ->autoconfigure()
        // Scalar constructor params named $projectRoot/$cacheDir across
        // services below are bound here by name, so each class only has
        // to type-hint `string $projectRoot` and gets the real path
        // without every service needing its own explicit args().
        ->bind('$projectRoot', '%spinx.project_root%')
        ->bind('$cacheDir', '%spinx.cache_dir%');

    $services->set(ViewFinder::class);
    $services->set(DirectiveCompiler::class);
    $services->set(TemplateCache::class);
    $services->set(Vite::class);
    $services->set(TemplateRenderer::class)
        ->public(); // public: controllers resolve this via autowiring, and the Kernel needs public() to fetch controllers directly

    // ConnectionManager is registered under its own interface ID via a
    // factory — see ConnectionManagerFactory's docblock for why the
    // concrete implementation (RoadRunner vs Swoole pool) is chosen at
    // runtime from spinx.json rather than hardcoded here.
    $services->set(ConnectionManagerFactory::class);
    $services->set(ConnectionManager::class)
        ->factory([service(ConnectionManagerFactory::class), 'create'])
        ->public();

    $services->set(\Doctrine\DBAL\Connection::class)
        ->factory([service(ConnectionManager::class), 'get'])
        ->public();

    $services->set(SchemaBuilder::class)
        ->public();

    $services->set(Migrator::class)
        ->public();

    // Real Symfony HttpClient factory — HttpClient::create() (not `new
    // HttpClient()`) is the correct usage: the class is a static
    // factory only, auto-detecting the fastest available transport
    // (curl if the extension is loaded, native streams otherwise).
    $services->set(\Symfony\Contracts\HttpClient\HttpClientInterface::class)
        ->factory([\Symfony\Component\HttpClient\HttpClient::class, 'create'])
        ->public();

    $services->set(\Spinx\Http\HttpClient::class)
        ->public();

    // Same factory pattern as HttpClientInterface above — MailerFactory
    // builds the right DSN (SMTP, Resend, Mailgun) from config/mail.php.
    $services->set(\Spinx\Mail\MailerFactory::class);
    $services->set(\Symfony\Component\Mailer\MailerInterface::class)
        ->factory([service(\Spinx\Mail\MailerFactory::class), 'create'])
        ->public();

    $services->set(\Spinx\Mail\Mailer::class)
        ->public();

    $services->set(\Spinx\Queue\QueueManager::class)
        ->public();

    // Session & Auth subsystem bindings
    $services->set(\Spinx\Session\FileSession::class)
        ->args(['%spinx.project_root%/storage/sessions'])
        ->public();

    $services->alias(\Spinx\Session\SessionInterface::class, \Spinx\Session\FileSession::class)
        ->public();

    $services->set(\Spinx\Auth\Middleware\AuthMiddleware::class)
        ->public();

    $services->set(\Spinx\Auth\Middleware\GuestMiddleware::class)
        ->public();

    // Logging subsystem bindings
    $services->set(\Spinx\Log\LogManager::class)
        ->public();

    $services->alias(\Psr\Log\LoggerInterface::class, \Spinx\Log\LogManager::class)
        ->public();

    // Cache subsystem bindings
    $services->set(\Spinx\Cache\CacheManager::class)
        ->args(['%spinx.project_root%'])
        ->public();

    $services->set(\Spinx\Cache\Store\CacheStoreInterface::class)
        ->factory([service(\Spinx\Cache\CacheManager::class), 'store'])
        ->public();
};

