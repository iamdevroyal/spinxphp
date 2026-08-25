# Runtime Drivers

Spinx runs on one of two runtime drivers, selected in `spinx.json`:

```bash
php spinx driver:swap roadrunner   # default
php spinx driver:swap swoole       # opt-in
```

Application code never touches the runtime directly — both implement
`Spinx\Runtime\ServerAdapter`, and every request reaches your controllers
as a plain Symfony `Request`/`Response` regardless of which is active.

## RoadRunner (default)

A Go binary supervises a pool of persistent PHP worker processes. No PHP
extension required — works natively on Windows, Linux, and macOS, which
is why it's the default. Concurrency comes from multiple worker
*processes*, not coroutines within one process — simpler to reason
about, no shared-state risk within a single worker beyond the usual
persistent-process rules (see [Architecture](architecture.md#state-safety)).

## Swoole (opt-in, Docker/Linux)

True coroutine concurrency within a single process — closer to Node's
event loop. Requires the Swoole/OpenSwoole PECL extension, which doesn't
build on Windows; the project's `Dockerfile` is the documented deploy
path.

**Fork safety is the sharp edge here.** Swoole's `$server->start()`
forks worker processes from the master. `Kernel::boot()` runs once
before that fork — safe *only* because `SwooleConnectionManager` is
lazy: it holds no live database connection until the first query
actually runs, which happens after fork, inside a worker. If that
manager ever became eager, connection setup would need to move into an
`onWorkerStart` handler instead. This reasoning lives directly in
`SwooleAdapter`'s class docblock, not just here.

**Connection pooling differs structurally, not just in config.**
RoadRunner workers are separate OS processes with no in-process
concurrency, so `RoadRunnerConnectionManager` just reuses one connection
per worker. Swoole coroutines share a single process, so
`SwooleConnectionManager` uses a real checkout/return pool via
`Swoole\Coroutine\Channel`. `ConnectionManagerFactory` reads `spinx.json`'s
`driver` key to pick the right one — **this is why running
`public/swoole-worker.php` while `spinx.json` still says `"roadrunner"`
is a real, if non-fatal, bug**, not just an inconsistency. The worker
script checks for and warns about exactly this mismatch at startup.

## What's verified vs. what needs a real machine

Neither Swoole nor its connection-pooling code could be installed and
executed in the environment this framework was built in (no PECL
access). What *was* verified: `SwooleAdapter::convertRequest()`/
`emitResponse()` — the request/response translation logic — tested
directly against stub `Swoole\Http\Request`/`Response` classes matching
their real method signatures, confirming header casing, multi-value
headers, cookies, status codes, and body content all map correctly. The
`boot()`/`serve()` server lifecycle itself is argued from Swoole's
documented fork behavior, not confirmed by execution — give it a real
smoke test on a machine with the extension installed before depending on
it in production.
