<?php

declare(strict_types=1);

namespace Spinx\Runtime;

use Nyholm\Psr7\Factory\Psr17Factory;
use Spiral\RoadRunner\Http\PSR7Worker;
use Spiral\RoadRunner\Worker as RoadRunnerWorker;
use Spinx\Kernel\Kernel;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Default Spinx runtime driver. A single Go-managed
 * RoadRunner binary spawns a pool of these PHP workers; each worker stays
 * alive across many requests, avoiding PHP-FPM's per-request bootstrap
 * cost entirely. No PHP extension required — this is what makes RoadRunner
 * the zero-friction, cross-OS default over Swoole.
 */
final class RoadRunnerAdapter implements ServerAdapter
{
    private Kernel $kernel;
    private PSR7Worker $worker;
    private HttpFoundationFactory $httpFoundationFactory;
    private PsrHttpFactory $psrHttpFactory;

    public function boot(Kernel $kernel): void
    {
        $this->kernel = $kernel;
        $this->kernel->boot();

        $psr17Factory = new Psr17Factory();
        $this->worker = new PSR7Worker(
            RoadRunnerWorker::create(),
            $psr17Factory,
            $psr17Factory,
            $psr17Factory
        );

        $this->httpFoundationFactory = new HttpFoundationFactory();
        $this->psrHttpFactory = new PsrHttpFactory($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory);
    }

    public function serve(): void
    {
        while (true) {
            try {
                $psrRequest = $this->worker->waitRequest();
            } catch (Throwable $e) {
                // Worker-level failure (e.g. malformed request from the
                // RoadRunner supervisor). Report and keep the worker alive.
                $this->worker->respond(
                    (new Psr17Factory())->createResponse(500)
                );
                continue;
            }

            if ($psrRequest === null) {
                // The RoadRunner supervisor is signaling this worker to
                // stop (graceful restart/recycle).
                break;
            }

            try {
                $symfonyRequest = $this->httpFoundationFactory->createRequest($psrRequest);
                $symfonyResponse = $this->handle($symfonyRequest);
                $psrResponse = $this->psrHttpFactory->createResponse($symfonyResponse);

                $this->worker->respond($psrResponse);
            } catch (Throwable $e) {
                $this->worker->getWorker()->error((string) $e);
            }
        }

        $this->shutdown();
    }

    public function handle(Request $request): Response
    {
        return $this->kernel->handle($request);
    }

    public function shutdown(): void
    {
        $this->kernel->shutdown();
    }
}
