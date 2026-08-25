<?php

declare(strict_types=1);

namespace Spinx\Runtime;

use Spinx\Kernel\Kernel;
use Swoole\Http\Request as SwooleRequest;
use Swoole\Http\Response as SwooleResponse;
use Swoole\Http\Server as SwooleHttpServer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Opt-in high-performance driver (build spec §2.3). Unlike RoadRunner,
 * there's no external supervisor binary here — this PHP process IS the
 * server, using Swoole's own worker-process model plus coroutines for
 * concurrency within each worker.
 *
 * FORK SAFETY (the sharpest edge of this adapter): Swoole's
 * $server->start() forks worker processes from the master. Anything
 * holding an open file descriptor or live connection at fork time gets
 * shared across all forked workers, which corrupts badly under
 * concurrent use. This is why boot() is safe to call ONCE before
 * serve()/start() here: Kernel::boot() compiles the DI container and
 * routes, but the ConnectionManager it wires up (see
 * Spinx\Database\Connection\SwooleConnectionManager) is deliberately
 * lazy — it holds no live connection until the first query actually
 * runs, which only happens after fork, inside a worker. If that manager
 * ever became eager, this class would need onWorkerStart to defer
 * connection setup instead.
 *
 * KNOWN LIMITATION: convertRequest() does not map uploaded files (Swoole
 * request->files) into Symfony UploadedFile objects — multipart file
 * upload support isn't wired up anywhere else in the framework yet
 * either, so this is a real gap to close together with that, not a
 * Swoole-specific oversight.
 */
final class SwooleAdapter implements ServerAdapter
{
    private Kernel $kernel;
    private SwooleHttpServer $server;

    public function __construct(
        private readonly string $host = '0.0.0.0',
        private readonly int $port = 9501,
        private readonly int $workerCount = 4,
    ) {
        if (!class_exists(SwooleHttpServer::class)) {
            throw new \RuntimeException(
                'SwooleAdapter requires the Swoole/OpenSwoole extension. ' .
                'Set "driver": "roadrunner" in spinx.json if it is not installed, ' .
                'or use the official Docker image which has it pre-installed.'
            );
        }
    }

    public function boot(Kernel $kernel): void
    {
        $this->kernel = $kernel;
        $this->kernel->boot(); // Safe before fork — see class docblock.

        $this->server = new SwooleHttpServer($this->host, $this->port);
        $this->server->set([
            'worker_num' => $this->workerCount,
            'enable_coroutine' => true,
        ]);

        $this->server->on('request', function (SwooleRequest $swooleRequest, SwooleResponse $swooleResponse): void {
            $this->onRequest($swooleRequest, $swooleResponse);
        });
    }

    public function serve(): void
    {
        $this->server->start(); // Blocks — forks worker_num workers, each running the coroutine event loop.
    }

    public function handle(Request $request): Response
    {
        return $this->kernel->handle($request);
    }

    public function shutdown(): void
    {
        $this->kernel->shutdown();
    }

    private function onRequest(SwooleRequest $swooleRequest, SwooleResponse $swooleResponse): void
    {
        try {
            $symfonyRequest = $this->convertRequest($swooleRequest);
            $symfonyResponse = $this->handle($symfonyRequest);
            $this->emitResponse($symfonyResponse, $swooleResponse);
        } catch (\Throwable $e) {
            $swooleResponse->status(500);
            $swooleResponse->end('Internal Server Error');
        }
    }

    public function convertRequest(SwooleRequest $swooleRequest): Request
    {
        $server = [];
        foreach ($swooleRequest->server ?? [] as $key => $value) {
            $server[strtoupper((string) $key)] = $value;
        }

        foreach ($swooleRequest->header ?? [] as $key => $value) {
            $server['HTTP_' . strtoupper(str_replace('-', '_', (string) $key))] = $value;
        }

        return new Request(
            $swooleRequest->get ?? [],
            $swooleRequest->post ?? [],
            [],
            $swooleRequest->cookie ?? [],
            [], // Uploaded files — see class docblock, not yet mapped.
            $server,
            $swooleRequest->rawContent() ?: null,
        );
    }

    public function emitResponse(Response $response, SwooleResponse $swooleResponse): void
    {
        $swooleResponse->status($response->getStatusCode());

        foreach ($response->headers->allPreserveCase() as $name => $values) {
            foreach ($values as $value) {
                $swooleResponse->header($name, $value);
            }
        }

        foreach ($response->headers->getCookies() as $cookie) {
            $swooleResponse->cookie(
                $cookie->getName(),
                $cookie->getValue() ?? '',
                $cookie->getExpiresTime(),
                $cookie->getPath(),
                $cookie->getDomain() ?? '',
                $cookie->isSecure(),
                $cookie->isHttpOnly(),
                $cookie->getSameSite() ?? '',
            );
        }

        $swooleResponse->end((string) $response->getContent());
    }
}
