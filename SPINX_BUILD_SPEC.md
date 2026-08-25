# Spinx — Build Specification

**Version:** v1 (MVP → V4 roadmap)
**Author:** Royal Nnaemeka Njoku (Spinx)
**Status:** Draft — pre-implementation

---

## 1. Vision & Positioning

Spinx is a PHP framework for building applications that don't need Laravel's
full weight, but demand near-Node.js performance, zero-friction cross-platform
installation, and an enforced Domain-Driven Design (DDD) architecture from the
first command run.

**Core pillars:**
- **Speed** — persistent-process runtime (RoadRunner default, Swoole opt-in),
  no per-request bootstrap cost
- **Portability** — runs on Windows, Linux, and macOS with a single install
  step, no compiled extensions required by default
- **Enforced architecture** — DDD module structure is not a convention, it is
  structurally the only way to add code to the framework
- **Frontend-agnostic, Vue-first** — templating compiles to plain HTML with
  hydration hooks; Vue ships by default with HMR, React is a swappable adapter
- **Native reach** — built-in desktop/mobile previewer, and a path to compile
  Spinx frontends into native mobile shells

**Explicitly out of scope for v1:** full Doctrine ORM, on-device PHP runtime
for offline mobile apps (see §9, Phase 2), shared-hosting/FPM support as a
primary deploy target.

---

## 2. Runtime Layer

### 2.1 Adapter interface
```
Spinx\Runtime\ServerAdapter
├── boot(): void
├── handle(Request): Response
└── shutdown(): void
```
All application code interacts with Symfony's `HttpFoundation` Request/Response
objects only. The adapter is solely responsible for bridging the underlying
runtime's native request/response objects into these.

### 2.2 RoadRunnerAdapter (default driver)
- Ships out of the box, zero manual install steps
- Composer post-install script downloads the correct RoadRunner binary for
  the host OS/architecture automatically
- Concurrency via a pool of persistent PHP worker processes managed by the Go
  supervisor binary
- Works natively on Windows, Linux, macOS — no compiled PHP extension needed

### 2.3 SwooleAdapter (opt-in, high-performance driver)
- Enabled via `spinx.json` config flag (`"driver": "swoole"`)
- True coroutine-based concurrency, closest to Node's event loop
- Requires the Swoole/OpenSwoole PECL extension — documented as a Docker/Linux
  deploy path, not supported natively on Windows
- Official Docker image published alongside the framework for this path

### 2.4 Requirements
- Both adapters must pass an identical conformance test suite (same
  Request/Response contract, same middleware pipeline behavior) so switching
  drivers never changes application-level behavior
- Adapter selection is a config value, never hardcoded into application code

---

## 3. Kernel

- Boots **once per process**, not per-request:
  - Compiles the Symfony DependencyInjection container (cached to disk)
  - Loads and compiles route definitions (cached to array/PHP file)
  - Registers module service providers (see §5)
- Provides a **request-scoped container**: a child container instantiated
  fresh per request, holding anything that must not leak state across
  coroutines (Swoole) or persist incorrectly across worker reuse
  (RoadRunner)
- Lifecycle hooks: `onBoot`, `onRequest`, `onShutdown`, `onWorkerError`

---

## 4. State Safety Layer

Persistent-process runtimes reuse memory across requests, which is the
single biggest correctness risk in this architecture. Spinx addresses this
directly rather than leaving it to developer discipline alone:

- **Static analysis rule** (custom PHPStan/Psalm rule shipped with the
  framework): flags any static property or singleton-scoped service that
  holds mutable, request-derived data
- **`RequestScope` container wrapper**: automatically resets/reallocates
  request-scoped services at the start of each request cycle
- **Documented "safe vs unsafe" service scoping guide**: every generated
  service via `spinx make:*` is scoped correctly by default (request-scoped
  unless explicitly marked singleton)

---

## 5. Enforced DDD Module System

This is the architectural core of Spinx. There is no bare
`app/Controllers` fallback — the kernel's autodiscovery only registers
services found inside the enforced module layout.

### 5.1 `spinx make:module <Name>` scaffold
```
app/Modules/<Name>/
├── Domain/
│   ├── Entities/
│   ├── ValueObjects/
│   ├── Events/
│   └── Repositories/        (interfaces only)
├── Application/
│   ├── Services/
│   └── Commands|Queries/
├── Infrastructure/
│   ├── Repositories/        (concrete implementations)
│   ├── Http/
│   │   ├── Controllers/
│   │   └── Middleware/
│   └── Persistence/
│       └── Migrations/
└── module.php                ← registers routes, DI bindings, migrations
```

### 5.2 Rules enforced by the kernel
- Controllers may only exist under a module's `Infrastructure/Http/Controllers`
- Domain layer must have zero dependencies on Infrastructure or Application
  (enforced via the static analysis rule from §4)
- Repository interfaces live in Domain, implementations in Infrastructure —
  bound together in `module.php` via the DI container
- Each module owns its own migrations, applied independently
  (`spinx module:migrate <Name>`)

### 5.3 Module registry
- `spinx.json` maintains a registry of active modules
- Modules can be toggled on/off without deletion (useful for feature-flagged
  or licensed modules in commercial Spinx apps)

---

## 6. Templating — "Spinx Directives"

### 6.1 Compilation model
- Directives (`@if`, `@foreach`, `@include`, `@module`, etc.) compile to
  **plain HTML** with `data-spinx-*` hydration hooks, not to PHP-only views
  — this is an islands-style architecture, not a Blade clone
- Compiled templates are cached, not re-parsed per request

### 6.2 Frontend integration
- **Vue ships as the default** frontend, scaffolded automatically by
  `spinx new`
- React is available via `spinx new --frontend=react`, using the same
  `data-spinx-*` mount-hook contract with a different Vite plugin preset
- Both integrate through a single Vite-based dev pipeline

### 6.3 Hot Module Reload (HMR)
- `spinx serve` boots the backend runtime (RoadRunner/Swoole) **and** the
  Vite dev server concurrently, proxied through a single port
- Frontend changes hot-reload without a full page refresh; backend route/
  controller changes trigger a worker reload (RoadRunner) or coroutine
  context refresh (Swoole)

### 6.4 Production build
- Vite compiles and bundles frontend assets as static output
- Static assets served directly by the PHP runtime adapter, no separate
  Node process required in production

---

## 7. Data Layer — Custom ORM

### 7.1 Foundation
- Built on **Symfony DBAL**, explicitly **not** full Doctrine ORM — Doctrine's
  UnitOfWork/proxy model is not coroutine-safe, which conflicts directly
  with the Swoole driver
- Custom fluent, Eloquent-style API layered on top of DBAL's connection and
  schema abstraction

### 7.2 Required feature parity (must-have, matching Laravel's Eloquent baseline)
- Fluent query builder: `where`, `orWhere`, `whereIn`, `with` (eager
  loading), `paginate`, `orderBy`, `groupBy`, `having`
- Relationships: `hasOne`, `hasMany`, `belongsTo`, `belongsToMany`,
  polymorphic relations
- Migrations: up/down, schema builder DSL, per-module migration scoping
  (see §5.3)
- Seeders and factories
- Model events/observers (`creating`, `created`, `updating`, etc.)
- Soft deletes, timestamps, casts (matching common Eloquent conventions)

### 7.3 Coroutine/worker safety
- Connection pooling implemented per-runtime-adapter:
  - RoadRunner: connection reused per worker process, reset between requests
  - Swoole: coroutine-aware connection pool, checked out/returned per
    coroutine to avoid cross-coroutine connection sharing

---

## 8. CLI

| Command | Purpose |
|---|---|
| `spinx new <project>` | Scaffold new app with enforced module dir, frontend, runtime config |
| `spinx make:module <Name>` | Generate full DDD module skeleton |
| `spinx make:controller <Module> <Name>` | Generate controller, module-scoped only |
| `spinx make:entity`, `make:service`, `make:repository` | Layer-scoped generators |
| `spinx serve` | Boot backend + Vite dev server with HMR |
| `spinx module:migrate <Name>` | Run a single module's migrations |
| `spinx preview --android \| --ios \| --desktop` | Launch native previewer (see §9) |
| `spinx build` | Production build (frontend bundle + backend cache compile) |
| `spinx driver:swap <roadrunner\|swoole>` | Switch runtime driver |

---

## 9. Desktop & Mobile Previewer

Spinx does not reimplement emulators — it orchestrates existing native
tooling, the same pattern proven by Expo/React Native CLI.

- `spinx preview --android` — launches Android Emulator via ADB (requires
  Android SDK installed on host), points it at the dev server with live
  reload
- `spinx preview --ios` — launches iOS Simulator via Xcode tooling
  (macOS + Xcode required, Apple's platform constraint, not Spinx's)
- `spinx preview --desktop` — opens a native webview window via a
  Go-based shell for quick desktop testing without a browser

---

## 10. Mobile Compilation

### 10.1 Path A — Native shell wrapper (v1 scope, shippable)
- Compiled Vue/React frontend assets wrapped in a **Go-built native shell**
  (`gomobile` bindings or a WebView-wrapper approach, conceptually similar
  to Capacitor/Tauri-mobile but Go-based instead of Rust/JS)
- App communicates with the Spinx backend over the network (REST/WebSocket)
- This is the committed v1 mobile story

### 10.2 Path B — On-device PHP runtime (Phase 2, NOT committed for v1)
- Would allow fully offline apps with PHP running on-device
- Closest existing building block: FrankenPHP (Go-based, embeds PHP via
  cgo) — but cross-compiling cgo to iOS/Android is fragile, and Apple's
  App Store review rules around embedded interpreters/dynamic code
  execution carry real approval risk
- **Requires a standalone feasibility spike before any commitment.** Not to
  be promised as a feature until that spike concludes successfully.

---

## 11. Packaging & Installation

- Distributed as a Composer package
- Post-install script:
  - Detects host OS/architecture
  - Downloads the correct RoadRunner binary automatically
  - Scaffolds `spinx.json` with sane defaults (RoadRunner driver, Vue
    frontend)
- Official Docker image published for the Swoole driver path
- No manual extension compilation required for the default install path

---

## 12. Build Order (MVP → V4)

1. **Kernel core** — RoadRunner adapter, routing, DI container compilation
2. **Enforced module system** — `make:module`, autodiscovery restricted to
   DDD layout, module-scoped DI/routes/migrations
3. **Request-scoped container / state safety layer**
4. **Templating & directive compiler** + Vite/Vue HMR pipeline
5. **Custom DBAL-based ORM** — query builder, relationships, migrations
6. **Full CLI generator set**
7. **Swoole adapter** (opt-in driver, Docker image)
8. **`spinx preview` orchestration** (Android/iOS/desktop)
9. **Go-based mobile shell compiler** (Path A)
10. **Docs + example apps** (raw HTML, Vue, React reference implementations)
11. *(Phase 2, post-v1)* On-device PHP feasibility spike (Path B)

---

## 13. Open Risks & Decisions Still Needed

| Item | Risk | Notes |
|---|---|---|
| Static analysis DDD enforcement | Medium | Needs a custom PHPStan/Psalm rule set built from scratch |
| Coroutine-safe connection pooling under Swoole | Medium | Needs careful design to avoid cross-coroutine leaks |
| Go-based mobile shell maturity | Medium-High | Less battle-tested than Capacitor/Tauri; budget time for platform quirks |
| On-device PHP (Path B) | High | Explicitly deferred pending feasibility spike |
| Cross-OS RoadRunner binary distribution | Low | Well-trodden pattern (similar to how esbuild/swc ship binaries via npm) |

---

## 14. Non-Goals for v1

- Full Doctrine ORM support
- Shared hosting / traditional PHP-FPM as a primary deploy target
- Offline on-device PHP mobile apps
- Non-Vue/React frontend adapters (Svelte, Angular, etc. — future
  consideration, not v1)
