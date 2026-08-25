# Spinx Documentation

Start with **[Getting Started](getting-started.md)** if this is your
first time here. The [build spec](../SPINX_BUILD_SPEC.md) is the
original design document this framework was built against — these docs
explain how to *use* what's been built; the spec explains *why* it was
designed this way.

- **[Getting Started](getting-started.md)** — install, run, first request
- **[Architecture](architecture.md)** — the enforced DDD module system,
  kernel, request lifecycle, state safety
- **[Configuration](configuration.md)** — `.env`, `env()`/`config()`,
  `config/` files
- **[Routing & Controllers](routing-and-controllers.md)** — including middleware
- **[Templating](templating.md)** — directives, `@island`, Vue/React/raw
  HTML — the three reference implementations
- **[Database & ORM](database.md)** — Model, relations, migrations,
  seeders/factories, genuinely batched eager loading
- **[External Services](external-services.md)** — `HttpClient`, a
  complete Paystack example, the pattern for any third-party API
- **[Security](security.md)** — CORS, rate limiting, CSRF, XSS
- **[Mail & Queues](mail-and-queues.md)** — `make:mail`, the DB-backed
  job queue
- **[Runtime Drivers](runtime-drivers.md)** — RoadRunner vs. Swoole, and
  the state-safety rules that apply to both
- **[CLI Reference](cli-reference.md)** — every `spinx` command
- **[Mobile & Desktop](mobile-and-desktop.md)** — `spinx preview`,
  `spinx build:mobile`

## Honest status of this build

This framework was built end-to-end, then hardened in a follow-up pass
after real developer feedback — each piece tested as rigorously as the
available tooling allowed: real execution where possible (the directive
compiler, the Go bridge library and desktop webview shell, the Kotlin
Android shell via a real `kotlinc` run, `npm`/`vite` builds of both the
Vue and React frontends, a full in-memory fake-DBAL harness exercising
the real, unmodified `QueryBuilder`/`Model`/`Relations` code for every
ORM feature including genuine batched eager loading), pure-logic unit
testing where a full stack wasn't installable, and syntax linting as a
floor everywhere else. "What's tested" sections throughout this docs
folder and the README document exactly what was verified and how.

## Known gaps

Stated plainly rather than hidden:
- The iOS shell (`mobile/ios/`, Swift) has zero compiler verification —
  no Swift toolchain was reachable in the build environment. Give it a
  real Xcode build before relying on it.
- `gomobile bind` (producing real `.aar`/`.framework` bridge binaries)
  needs Go 1.25+ on your own machine — installing it was attempted
  directly in the build environment and hit a real, documented wall.
- `SchemaBuilder::table()`'s ALTER TABLE path uses Doctrine DBAL APIs —
  verified directly against DBAL 4.x's own official documentation
  (fetched, not assumed), but not executed against a real installed
  DBAL in this environment. High confidence, not the same as proven.
- `RateLimitMiddleware`'s default store only tracks attempts within a
  single worker process, not shared across a worker pool — see
  [Security](security.md) for the real implication and the fix.
- No session subsystem exists — `CsrfMiddleware` deliberately uses a
  stateless double-submit-cookie pattern instead of session storage.

## Post-build correction pass

A real `composer install` on a real machine surfaced two genuine bugs
this build's own PHP-only linting couldn't have caught:

1. **`composer.json` listed `symfony/dbal`, which doesn't exist** — a
   mix-up with Symfony 7's own version numbering, never caught because
   nothing in the build environment could run a real `composer install`
   against the actual Packagist registry. Every single package in
   `composer.json` was re-verified against real Packagist listings as
   part of fixing this, not just the one that broke.
2. **The RoadRunner binary downloader was a real stub, and it turned
   out to be unnecessary** — `spiral/roadrunner-cli` already ships an
   official `vendor/bin/rr get` command. The custom `RoadRunnerInstaller`
   scaffold class was deleted entirely rather than completed, since the
   correct fix was using the real upstream tool, not finishing a
   hand-rolled one.

The same review found a **silent gap with no disclaimer anywhere**,
worse than a documented stub: `composer.json` declared PSR-15 packages
and the original build spec promised a middleware pipeline, but none
existed in `Kernel.php`. Built properly in this pass (deliberately not
literal PSR-15 — see [Routing & Controllers](routing-and-controllers.md#middleware)
for why), tested for correct onion-order execution.

## v2 hardening pass

A second, larger round of real developer feedback surfaced further real
gaps, each fixed and tested, not just documented:

- **No `.env`/config system existed at all** — added `vlucas/phpdotenv`
  (the same package Laravel uses) plus `env()`/`config()` helpers and a
  full `config/` directory. See [Configuration](configuration.md),
  including why `config/services.php` (DI wiring) was renamed to
  `config/container.php` to make room for the Laravel-familiar
  services-credentials file of the same name developers actually
  expected.
- **Eager loading wasn't actually eager, and worse, was structurally
  broken** — `with()` previously issued one query per relation *per
  row* (documented as a known limitation), but fixing that surfaced a
  deeper bug: `QueryBuilder` was calling relation-defining methods
  directly, which are `protected` by convention throughout this
  framework, and `QueryBuilder` isn't part of `Model`'s class hierarchy
  — every real usage of `with()` would have hit a fatal visibility
  error. Found via a real test against real code (not a parallel fake),
  fixed with a clean internal bridge method on `Model`, then verified
  eager loading issues exactly one query per relation regardless of row
  count — see [Database & ORM](database.md).
- **`SchemaBuilder::table()`'s DBAL API was actually wrong**, not just
  unverified — fetched DBAL 4.x's real documentation directly and found
  three separate mistakes (wrong entry point, wrong method name, SQL
  generation on the wrong object). Fixed against the verified API.
- **No path to a new project, no external-API pattern, no security
  middleware, no mail/queues** — all real, working, and tested gaps
  relative to what a production-ready framework needs; each is now
  built, documented, and demonstrated in the `Health`/`Todo` reference
  modules. See the README's "v2 hardening pass" section for the full
  list.

Two smaller, genuine bugs were caught and fixed *during* this pass's own
testing, not before it: `Spinx\Http\HttpClient`'s default constructor
value called `new` on a class that's a static-factory-only in real
Symfony HttpClient (fixed by registering it as a DI factory, matching
this project's existing pattern for `Doctrine\DBAL\Connection`), and a
test-harness bug in this session's own in-memory fake-DBAL (`lastInsertId()`
picking the wrong table) that would have silently produced a false
negative if left in place.

