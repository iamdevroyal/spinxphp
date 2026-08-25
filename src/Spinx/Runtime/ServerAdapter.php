<?php

declare(strict_types=1);

namespace Spinx\Runtime;

use Spinx\Kernel\Kernel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Contract every Spinx runtime driver (RoadRunner, Swoole, ...) must satisfy.
 *
 * Application code NEVER talks to a driver directly. Controllers, services,
 * and modules only ever see Symfony's Request/Response objects. The adapter
 * is solely responsible for translating the underlying runtime's native
 * request/response representation into and out of that contract.
 *
 * Swapping "driver" in spinx.json must never change application behavior —
 * every adapter is expected to pass the shared conformance test suite in
 * tests/Runtime/AdapterConformanceTest.php.
 */
interface ServerAdapter
{
    /**
     * Called once when the persistent process starts. Boots the Kernel,
     * compiles the container/route cache, and prepares the adapter to
     * begin accepting requests.
     */
    public function boot(Kernel $kernel): void;

    /**
     * Blocks and serves requests, translating each inbound request into a
     * Symfony Request, dispatching it through the Kernel, and writing the
     * resulting Symfony Response back out through the native runtime.
     *
     * This method owns the main server loop for the lifetime of the process.
     */
    public function serve(): void;

    /**
     * Translate a single Symfony Request through the Kernel. Exposed
     * separately from serve() so adapters and tests can dispatch a single
     * request without needing a live server loop.
     */
    public function handle(Request $request): Response;

    /**
     * Called on graceful shutdown (SIGTERM, worker recycle, fatal error).
     * Adapters should release connections/resources here, not rely on
     * process death to clean up, since the process may be long-lived.
     */
    public function shutdown(): void;
}
