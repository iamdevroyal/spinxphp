# Spinx

A fast, lightweight PHP framework with enforced DDD architecture.
Applications are built the same way regardless of runtime — Spinx runs
on RoadRunner by default (zero-config, cross-platform) or Swoole
(opt-in, for coroutine-level concurrency on Docker/Linux), but neither
is the point of the framework; they're interchangeable execution
backends behind one `Spinx\Runtime\ServerAdapter` contract, chosen with
a single `spinx driver:swap` command and otherwise invisible to
application code. See `SPINX_BUILD_SPEC.md` for the full design spec —
this README covers what's implemented and how to run it.

**Post-v2 hardening pass:** real `composer install` and real developer
workflow surfaced a fake package name, a missing middleware pipeline, an
eager-loading implementation that wasn't actually eager, `.env`/config
support that didn't exist yet, and no path to starting a new project.
All fixed — full account in
[`docs/README.md`](docs/README.md#post-build-correction-pass).

## Build status: v1 core complete, v2 hardening in progress

All ten original build-order steps are implemented, plus a substantial
hardening pass covering environment config, a genuinely batched ORM,
security middleware, external-service integration, and mail/queues:


1. Kernel + RoadRunner adapter + routing + DI
2. Enforced DDD module system + `make:module`
3. Request-scoped container safety layer
4. Templating/directive compiler + Vite/Vue HMR pipeline
5. Custom DBAL-based ORM
6. Full CLI generator set
7. Swoole adapter (opt-in)
8. `spinx preview` orchestration
9. Go-based mobile shell compiler
10. Docs + example apps

**v2 hardening pass** (this update) added:
- `spinx new <project> [--frontend=vue|react]` — was a documented gap, now real
- `.env` + `config/*.php` (Laravel-familiar pattern) — `env()`/`config()` helpers,
  see [`docs/configuration.md`](docs/configuration.md)
- **Genuinely batched eager loading** — `with()` now issues one `WHERE IN`
  query per relation regardless of row count, not one query per row (a
  real bug: `QueryBuilder`'s eager loading also couldn't call the
  protected relation-defining methods it needed to call at all — see the
  correction pass notes)
- 15 additional Eloquent-style methods: `firstOrCreate`, `updateOrCreate`,
  `exists`, `pluck`, `value`, `chunk`, `increment`/`decrement`,
  `latest`/`oldest`, `whereNull`/`whereBetween`, `fresh`/`refresh`,
  `only`/`except`, and more — see [`docs/database.md`](docs/database.md)
- `Spinx\Http\HttpClient` — the pattern for calling external APIs
  (Paystack, Stripe, Resend, Mailgun, anything), see
  [`docs/external-services.md`](docs/external-services.md)
- Security middleware: CORS, rate limiting, CSRF (`@csrf` directive) —
  see [`docs/security.md`](docs/security.md)
- Mail + a real DB-backed queue: `spinx make:mail`, `spinx queue:work` —
  see [`docs/mail-and-queues.md`](docs/mail-and-queues.md)
- `make:module --all` / `--except=x,y` — provision entity/model/repository/
  service/controller/migration in one command
- CLI moved from `bin/spinx` to a root-level `spinx` script — `php spinx serve`,
  matching Laravel's `php artisan` convention

**Full documentation lives in [`docs/`](docs/README.md)** — architecture,
routing, templating (including all three reference implementations),
database/ORM, configuration, external services, security, mail/queues,
runtime drivers, CLI reference, and mobile/desktop. Start there, not this
README, for anything beyond a quick orientation.

### Step 10 additions

- **`docs/`** — eight structured guides, cross-referenced with the
  actual verification each subsystem got during the build
- **Three reference implementations, all real and tested:**
  - Vue (default) — `Health`/`Welcome` at `/`, built in step 4
  - **React** — `examples/react-frontend/`, built this step: real
    `npm install` (63 packages) and a real `vite build` producing a
    working manifest + hashed bundle. This is what actually proves
    "frontend-agnostic" rather than just asserting it
  - **Raw HTML, zero JS framework** — the new `Todo` module at
    `/todos`, using only `@if`/`@foreach`/`{{ }}`, deliberately zero
    `@island` calls
- **Two real bugs found and fixed while building the React example and
  the raw-HTML example** — not written up after the fact, but caught in
  the act of actually testing:
  - `Spinx\Templating\Vite`'s production manifest path was wrong since
    step 4 (`public/build/manifest.json` instead of the real Vite 5
    location, `public/build/.vite/manifest.json`) — never caught earlier
    because no one had actually run `vite build` until this step
  - `DirectiveCompiler`'s echo regexes could silently corrupt distant,
    unrelated content if a template ever contained literal prose like
    `{{ }}` (documenting the syntax rather than using it) — found while
    writing the `Todo` module's own explanatory text, fixed at the
    regex level (not just worked around in that one template), and
    confirmed against the full original step 4 regression suite with no
    other change in behavior
- **`spinx build`** and closed a gap noticed while fixing the above:
  production build command (frontend assets + primed backend cache),
  tested end-to-end through the real CLI
- **Known gap, stated rather than hidden**: `spinx new <project>` — a
  standalone project scaffolder — was never built across any step. This
  repository doubles as the reference project instead. See
  [`docs/cli-reference.md`](docs/cli-reference.md#known-gap-spinx-new)
  for the current workaround.

## Quickstart

```bash
composer install
cp .env.example .env
cd frontend && npm install && cd ..
vendor/bin/rr get
php spinx migrate
php spinx serve
```

Then visit `http://localhost:8080/` (Vue), `http://localhost:8080/todos`
(raw HTML, zero JS framework, also the CSRF middleware reference), or
`http://localhost:8080/health` (JSON, exercises the ORM, also the CORS +
rate-limiting + security-headers middleware reference). Full guide:
**[`docs/getting-started.md`](docs/getting-started.md)**.

Starting a brand new project instead of using this one:
```bash
php spinx new my-app --frontend=vue   # or --frontend=react
```

## What's tested vs. what needs local verification

**Doctrine DBAL** — no Packagist access, so real `doctrine/dbal` itself
was never installed here. What *was* built and used extensively in the
v2 hardening pass: a functional in-memory fake at the DBAL boundary
(`Connection`/`QueryBuilder`/`Result`) matching Doctrine's real API
shape, letting the actual, unmodified `QueryBuilder.php`/`Model.php`/
`Relations/*.php` run against real SQL-like semantics — every CRUD
method, every relation type's eager-loading batching (including a
query-count assertion, not just correctness), and all 15+ Eloquent-style
convenience methods were verified this way, not just linted.
`SchemaBuilder::table()`'s DBAL API was verified directly against DBAL
4.x's own official documentation (fetched, not assumed) after being
found genuinely wrong on three separate points — high confidence, but
still not executed against a real installed DBAL. The `create()` path
is stable and has always been confident.

**Swoole** — not installable in this sandbox either (no
PECL/extension access). What I *did* test: `SwooleAdapter::convertRequest()`
and `emitResponse()` against stub `Swoole\Http\Request`/`Response` and stub
Symfony classes matching their real method signatures — verified header
casing, multi-value headers, cookies, status codes, and body content all
map correctly. The `boot()`/`serve()` lifecycle and the fork-safety
reasoning below are argued from Swoole's documented behavior, not
verified by execution.

## Swoole adapter — fork safety (read before enabling)

Swoole's `$server->start()` forks worker processes from the master.
Anything holding a live connection or open file descriptor at fork time
gets shared across every forked worker, which corrupts badly under
concurrent use. `Kernel::boot()` is safe to call once before `start()`
here specifically because `SwooleConnectionManager` (step 5) is lazy — it
opens no real database connection until the first query actually runs,
which only happens after fork, inside a worker. If that manager ever
became eager, `SwooleAdapter::boot()` would need to move connection setup
into an `onWorkerStart` handler instead. This is documented directly in
`SwooleAdapter`'s class docblock, not just here.

Related: `ConnectionManagerFactory` (step 5) reads spinx.json's
`"driver"` key to decide RoadRunner-style vs. Swoole-coroutine-pool
connection handling — so running `public/swoole-worker.php` while that
config still says `"roadrunner"` gives the app the wrong pooling strategy
for the server actually running. `swoole-worker.php` checks for and warns
about exactly this mismatch at startup; use `spinx driver:swap swoole` to
avoid it entirely.

## Swoole quickstart

```bash
php spinx driver:swap swoole
docker build -t spinx-app .
docker run -p 9501:9501 spinx-app
curl http://localhost:9501/health
```

For local (non-Docker) Swoole development on Linux/macOS/WSL2:

```bash
pecl install swoole   # or openswoole
php spinx driver:swap swoole
php spinx serve
```

## Database quickstart

SQLite is the zero-config default (`storage/database.sqlite`, created
automatically) — no separate database server needed to get started.
MySQL/Postgres are a `.env` change away (see `config/database.php`):

```
DB_DRIVER=pdo_mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=myapp
DB_USERNAME=root
DB_PASSWORD=
```

Run migrations, then use the model:

```bash
composer install
php spinx migrate
php spinx serve
curl http://localhost:8080/health   # each hit now logs a row via HealthCheckLog
```

Writing a migration (`app/Modules/<Name>/Infrastructure/Persistence/Migrations/`,
timestamp-prefixed filename):

```php
<?php
use Spinx\Database\Migration;
use Spinx\Database\Schema\Blueprint;
use Spinx\Database\Schema\SchemaBuilder;

return new class extends Migration {
    public function up(SchemaBuilder $schema): void
    {
        $schema->create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id');
            $table->decimal('total');
            $table->timestamps();
        });
    }

    public function down(SchemaBuilder $schema): void
    {
        $schema->dropIfExists('orders');
    }
};
```

Writing a model (`app/Modules/<Name>/Infrastructure/Persistence/Models/`):

```php
final class Order extends Model
{
    protected array $fillable = ['customer_id', 'total'];
    protected array $casts = ['total' => 'float'];

    protected function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}

$order = Order::create(['customer_id' => 1, 'total' => 42.50]);
$order->customer; // lazy-loaded via magic __get
Order::query()->where('total', '>', 100)->with('customer')->get();
```

## State safety in practice

Every service a module registers in `module.php` is request-scoped by
default — reset fresh at the start of every request. To opt a genuinely
stateless service out (so it persists across requests on the same
worker/coroutine for a small performance win):

```php
use Spinx\Container\Attribute\Singleton;

#[Singleton]
final class PriceFormatter
{
    // No mutable per-request state — safe to reuse across requests.
}
```

Static properties bypass container scoping entirely, so they're covered by
a separate mechanism — a custom PHPStan rule that flags any non-readonly
static property inside `app/Modules`:

```bash
composer install
vendor/bin/phpstan analyse
```


## CLI generator quickstart

```bash
php spinx make:module Orders
php spinx make:entity Orders Order
php spinx make:model Orders Order
php spinx make:repository Orders Order
php spinx make:service Orders PlaceOrder
php spinx make:controller Orders ListOrders
php spinx make:migration Orders create_orders_table
```

`make:controller`, `make:service`, and `make:repository` print a
copy-paste snippet for `module.php` after generating — routes and DI
bindings are **not** auto-inserted, deliberately: `module.php` is real
PHP, not a data file, and programmatically rewriting someone's
hand-tuned route table risks corrupting it. The snippet is the exact
code to paste in.

`spinx make:module <Name>` alone (no other args) scaffolds the full
enforced layer layout, a `module.php` stub, a per-module `README.md`
explaining the layer boundaries, and registers the module in
`spinx.json`. Module names must be StudlyCase (`Orders`,
`BillingAccounts`) — validated, since the name maps directly onto the
`App\Modules\<Name>\...` namespace.

## Setup

```bash
cd spinx
composer install
cd frontend && npm install && cd ..
```

Fetch the RoadRunner binary using the official installer (ships as a
`require-dev` dependency, `spiral/roadrunner-cli` — this is the real,
upstream-documented way to get it, not a custom downloader):

```bash
vendor/bin/rr get
```

This downloads the correct `rr`/`rr.exe` for your platform into the
project root and needs `ext-curl`/`ext-zip` enabled (standard in most PHP
installs). If you'd rather fetch it by hand: download from
https://github.com/roadrunner-server/roadrunner/releases, place it in
the project root next to `.rr.yaml`, and on Linux/macOS run `chmod +x rr`.

Then run migrations and boot the server:

```bash
php spinx migrate
php spinx serve
```

Verify it's alive:

```bash
curl http://localhost:8080/health
# {"status":"ok","driver":"roadrunner","module":"Health"}
```

Check the response headers too — `X-Spinx-Middleware: SecurityHeadersMiddleware ran`
should be present, proving the middleware pipeline (see
[docs/routing-and-controllers.md](docs/routing-and-controllers.md)) actually executed.

Visit `http://localhost:8080/` for the full templating + Vue island demo.
For the Swoole driver instead, see "Swoole quickstart" above.

## Project layout

```
spinx/
├── app/Modules/               ← DDD-enforced application code (see Health/, Todo/ for reference layouts)
├── config/                    ← app config (database.php, mail.php, services.php, cors.php, ...) + container.php (DI wiring only)
├── database/migrations/       ← framework-internal migrations (e.g. queue tables) — see docs/database.md
├── docs/                      ← full documentation, start at docs/README.md
├── examples/react-frontend/   ← the React reference implementation
├── frontend/                  ← Vue + Vite scaffold (default), island-based hydration
├── public/worker.php          ← RoadRunner process entrypoint
├── public/swoole-worker.php   ← Swoole process entrypoint (opt-in driver)
├── resources/views/           ← global .spinx.html templates
├── src/Spinx/                 ← framework internals
├── storage/                   ← compiled caches, SQLite DB, Vite hot marker (gitignored contents)
├── tools/                     ← desktop preview shell + mobile shell bridge (Go)
├── .env.example                ← copy to .env — see docs/configuration.md
├── .rr.yaml                     ← RoadRunner server config
├── Dockerfile                   ← Swoole deploy path
├── spinx.json                    ← runtime driver + frontend choice + module registry (NOT app config — see docs/configuration.md)
└── spinx                        ← CLI (php spinx serve, like Laravel's php artisan)
```


