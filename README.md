<div align="center">

# Spinx Framework

**The Modern High-Performance PHP Framework for Persistent Workers, Enforced DDD Architecture, and Reactive Island Hydration.**

[![Latest Version](https://img.shields.io/badge/release-v1.0.11-6366f1.svg?style=flat-square)](https://github.com/iamdevroyal/spinxphp)
[![Documentation](https://img.shields.io/badge/docs-spinxphp.pages.dev%2Fdocs-ec4899.svg?style=flat-square)](https://spinxphp.pages.dev/docs)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.2-8b5cf6.svg?style=flat-square)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)](LICENSE)
[![Build Status](https://img.shields.io/badge/tests-92%2F92%20passing-10b981.svg?style=flat-square)](https://github.com/iamdevroyal/spinxphp)

</div>

---

## ⚡ Why Spinx?

Traditional PHP frameworks run on PHP-FPM, destroying and recreating the application lifecycle on every incoming HTTP request. Spinx runs inside **long-lived persistent execution workers** (RoadRunner by default, Swoole coroutines opt-in). Route compilation, dependency injection reflection, configuration parsing, and database schemas remain warmed in RAM across requests — delivering **sub-millisecond latencies and massive throughput**.

### Core Pillars

- 🚀 **Extreme Persistent-Worker Performance**: Powered by a unified `ServerAdapter` contract with zero per-request bootstrap cost.
- 🏗️ **Kernel-Enforced DDD Architecture**: Code must live within structured Domain-Driven Design modules (`app/Modules/<Name>/module.php`). Loose files in global folders are rejected at boot.
- 🛡️ **Zero-Leak Memory Safety**: `RequestScope` container resets and custom PHPStan static analysis rules eliminate cross-request memory contamination.
- ⚡ **Fluent Route DSL & Alias Registry**: Clean, expressive routing with automatic DI autowiring.
- 🔐 **State-Safe Auth & Sessions**: Integrated request-isolated session drivers (File & Database) and bcrypt authentication.
- 🗄️ **DBAL 4 Active Record ORM & Schema Cache**: Compiled ahead-of-time schema column caching, selective column querying (`selectWithout`), conditional query chaining (`when/then/else`), platform-aware `upsert`, and transaction row locking (`atomic`).
- ⏱️ **In-Framework Task Scheduler**: Fluent cron scheduling in `schedule.php` executed via a single `spinx schedule:run` command.
- 📖 **OpenAPI 3.1 Generator**: Auto-generate OpenAPI schemas via route reflection and PHP 8 attributes.
- 🏝️ **Reactive Island Hydration**: Server-rendered HTML views with targeted client-side component hydration (`@island`) for Vue 3 and React 19.
- 📱 **Mobile Preview & Native Shells**: Interactive browser-based mobile preview container (`spinx preview --mobile`) and native Android (Kotlin) / iOS (Swift) shell generators.

---

## 📦 Installation & Quickstart

Create a new Spinx project with a single command:

```bash
# 1. Create a new Spinx application
spinx new my-app --frontend=vue

# 2. Enter project directory
cd my-app

# 3. Boot backend persistent server + Vite HMR dev server
spinx serve
```

### System Requirements

- **PHP**: `>= 8.2` (uses typed properties, readonly classes, and enums)
- **Extensions**: `ext-mbstring`, `ext-pdo` (or `pdo_sqlite` / `pdo_mysql` / `pdo_pgsql`)
- **Node.js**: `>= 18.0` (for Vite frontend asset pipeline)

---

## 🧩 Enforced DDD Module Architecture

Spinx eliminates messy global folders by enforcing Domain-Driven Design (DDD) boundaries at the kernel level.

```bash
spinx make:module Billing
```

This scaffolds the following structured architecture:

```
app/Modules/Billing/
├── Domain/
│   ├── Entities/            (pure domain logic, zero infrastructure dependencies)
│   ├── ValueObjects/
│   ├── Events/
│   └── Repositories/        (interfaces only)
├── Application/
│   ├── Services/            (use-case orchestration)
│   └── Jobs/                (queueable asynchronous tasks)
├── Infrastructure/
│   ├── Repositories/        (concrete DBAL repository implementations)
│   ├── Http/
│   │   ├── Controllers/     (invokable HTTP controllers)
│   │   └── Middleware/      (request middlewares)
│   └── Persistence/
│       ├── Models/          (Active Record models)
│       └── Migrations/      (timestamped schema migrations)
└── module.php               (declarative routes, aliases, and DI container wiring)
```

---

## 🚦 Fluent Routing DSL & Alias Registry

Declare your module's controllers, middlewares, routes, and services cleanly inside `app/Modules/<Name>/module.php`:

```php
use App\Modules\Billing\Infrastructure\Http\Controllers\InvoiceController;
use Spinx\Auth\Middleware\AuthMiddleware;
use Spinx\Routing\{AliasRegistry, Route, RouteBuilder};
use Symfony\Component\DependencyInjection\ContainerBuilder;

return [
    // Register controller aliases (auto-wired into Symfony DI container):
    'controllers' => static function (AliasRegistry $r): void {
        $r->registerController('invoice_show', InvoiceController::class);
    },

    // Register middleware aliases:
    'middlewares' => static function (AliasRegistry $r): void {
        $r->registerMiddleware('auth', AuthMiddleware::class);
    },

    // Define routes using fluent DSL:
    'routes' => static function (RouteBuilder $routes): void {
        Route::get(['invoices.show', '/invoices/{id}'])
            ->middleware(['auth'])
            ->controller('invoice_show');

        Route::group('/api/v1', function (RouteBuilder $group): void {
            Route::post(['invoices.create', '/invoices'])->controller('invoice_create');
        });
    },

    // Register module services into Symfony DI:
    'services' => static function (ContainerBuilder $container, string $moduleDir): void {
        $container->register(InvoiceRepositoryInterface::class, InvoiceRepository::class)
            ->setAutowired(true)
            ->setPublic(true);
    },
];
```

---

## 🗄️ Database & Active Record ORM

Spinx ORM is built on top of Doctrine DBAL 4, providing familiar active-record ergonomics with persistent-worker performance.

```php
namespace App\Modules\Billing\Infrastructure\Persistence\Models;

use Spinx\Database\Model;

final class Invoice extends Model
{
    protected static string $table = 'invoices';
    protected array $fillable = ['customer_id', 'amount', 'status'];
    protected array $casts = ['amount' => 'float'];
}
```

### Pre-Compiled Schema Cache (`spinx schema:compile`)

Introspect tables ahead of time and load column mappings directly into OpCache:

```bash
spinx schema:compile
```

### Advanced Querying

```php
use App\Modules\Billing\Infrastructure\Persistence\Models\Invoice;
use Spinx\Database\DB;

// 1. Column filtering backed by pre-compiled SchemaCache:
$invoices = Invoice::query()
    ->selectWith('id', 'amount', 'status')
    ->get();

$users = User::query()
    ->selectWithout('password', 'remember_token')
    ->get();

// 2. Conditional query builder (when / then / else / otherwise):
$results = Invoice::query()
    ->where('status', 'active')
    ->when($isAdmin)
        ->then(fn($q) => $q->where('include_internal', true))
        ->else(fn($q) => $q->where('is_public', true))
    ->get();

// 3. Platform-aware atomic upsert:
Invoice::upsert(
    values: ['id' => 101, 'amount' => 450.00, 'status' => 'paid'],
    uniqueColumns: ['id'],
    updateColumns: ['amount', 'status']
);

// 4. Row locking inside transactions (SELECT FOR UPDATE):
Invoice::atomic($invoiceId, function (Invoice $invoice): void {
    $invoice->update(['status' => 'settled']);
});

// 5. DB Static Façade for transactions:
DB::transaction(function ($conn): void {
    DB::statement('UPDATE accounts SET balance = balance - 100 WHERE id = :id', ['id' => 1]);
    DB::statement('UPDATE accounts SET balance = balance + 100 WHERE id = :id', ['id' => 2]);
});
```

---

## 🔒 Authentication & Session Subsystem

Designed specifically to prevent memory leaks and session fixation attacks in persistent runtimes:

```php
use Spinx\Auth\{Auth, Hash};

// 1. Bcrypt Password Hashing:
$hashed = Hash::make('secret_password', cost: 12);
$isValid = Hash::check('secret_password', $hashed);

// 2. Attempt Login (auto-regenerates session ID for fixation protection):
if (Auth::attempt(['email' => $email, 'password' => $password])) {
    $user = Auth::user();
    $userId = Auth::id();
}

// 3. Check State & Logout:
if (Auth::check()) {
    // User is logged in
}

Auth::logout();
```

---

## 🧪 Data Validation Engine

```php
use Spinx\Validation\Validator;

$validated = Validator::make($request->request->all(), [
    'name'     => 'required|string|max:100',
    'email'    => 'required|email',
    'password' => 'required|min:8|confirmed',
    'tier'     => 'required|in:free,pro,enterprise',
    'bio'      => 'nullable|string|max:500',
])->validate(); // Returns strictly allowed attributes; drops extraneous keys
```

---

## ⏱️ In-Framework Task Scheduler

Define cron jobs fluently in `schedule.php`:

```php
use Spinx\Schedule\Scheduler;

return function (Scheduler $scheduler, $container): void {
    // Run daily at 03:00 AM:
    $scheduler->call(function () use ($container) {
        $container->get(CleanupService::class)->run();
    }, 'daily cleanup')->daily('03:00');

    // Run every 15 minutes:
    $scheduler->call(fn() => syncInventory(), 'inventory sync')->everyMinutes(15);

    // Run every Monday at 08:30:
    $scheduler->call(fn() => sendWeeklyReport(), 'weekly report')->weekly(1, '08:30');
};
```

Run due tasks via one OS cron entry:
```bash
* * * * * cd /path/to/app && php spinx schedule:run >> /dev/null 2>&1
```

---

## 📖 OpenAPI 3.1 Spec Generator

Annotate your controllers with native PHP 8 attributes:

```php
namespace App\Modules\Billing\Infrastructure\Http\Controllers;

use Spinx\OpenApi\Attributes\{ApiSummary, ApiParam, ApiResponse, ApiTag};
use Symfony\Component\HttpFoundation\{Request, JsonResponse};

#[ApiTag('Invoices')]
#[ApiSummary('Retrieve invoice details')]
#[ApiParam(name: 'id', in: 'path', type: 'integer', description: 'Invoice ID')]
#[ApiResponse(status: 200, description: 'Invoice data returned')]
#[ApiResponse(status: 404, description: 'Invoice not found')]
final class InvoiceShowController
{
    public function __invoke(Request $request, int $id): JsonResponse
    {
        return new JsonResponse(['id' => $id, 'status' => 'paid']);
    }
}
```

Generate the OpenAPI 3.1 JSON schema:
```bash
spinx openapi:generate --output=public/openapi.json
```

---

## 🏝️ Reactive Island Hydration

Server-render your HTML views with ultra-fast native templates and selectively hydrate Vue 3 or React 19 components on the client:

```html
<div class="card">
    <h1>Project Metrics</h1>
    <p>Server rendered timestamp: {{ date('Y-m-d H:i') }}</p>

    <!-- Client-side reactive island hydrated via Vite -->
    @island('MetricsChart', ['projectId' => $project->id])
</div>
```

---

## 📱 Mobile Device Preview Tool

Spinx includes a built-in browser-based interactive mobile device container for testing responsive views across simulated iPhone and Android viewports:

```bash
spinx preview --mobile
```

To scaffold native WebView shells:
```bash
# Android shell (Kotlin + WebView):
spinx build:mobile --android

# iOS shell (Swift + WKWebView):
spinx build:mobile --ios
```

---

## 🛠️ Complete CLI Command Reference

| Command | Description |
|---|---|
| `spinx new <project>` | Scaffold a brand new Spinx project |
| `spinx serve` | Boot backend server (RoadRunner/Swoole) + Vite dev server (HMR) |
| `spinx driver:swap <driver>` | Switch runtime driver (`roadrunner` or `swoole`) |
| `spinx make:module <Name>` | Scaffold a complete DDD module skeleton |
| `spinx make:controller <Mod> <Name>` | Generate controller in module Infrastructure layer |
| `spinx make:entity <Mod> <Name>` | Generate Domain entity |
| `spinx make:service <Mod> <Name>` | Generate Application service |
| `spinx make:repository <Mod> <Name>` | Generate repository interface & implementation |
| `spinx make:model <Mod> <Name>` | Generate ORM model in Infrastructure layer |
| `spinx make:middleware <Mod> <Name>` | Generate middleware class |
| `spinx make:migration <Mod> <desc>` | Generate timestamped database migration |
| `spinx make:mail <Mod> <Name>` | Generate Mailable + view + queueable Job |
| `spinx migrate [Name]` | Run pending database migrations |
| `spinx module:migrate <Name>` | Run pending migrations for one module |
| `spinx schema:compile` | Compile schema into `storage/cache/schema_columns.php` |
| `spinx queue:work` | Poll and process database-backed job queue |
| `spinx schedule:run` | Run all tasks in `schedule.php` due right now |
| `spinx openapi:generate` | Generate OpenAPI 3.1 specification schema |
| `spinx preview --mobile` | Open browser-based interactive mobile device container |
| `spinx preview --android` | Open dev server on connected Android device/emulator |
| `spinx preview --ios` | Open dev server on iOS Simulator (macOS + Xcode) |
| `spinx preview --desktop` | Open dev server in native desktop webview window |
| `spinx build:mobile --android` | Scaffold native Android shell in `mobile/android/` |
| `spinx build:mobile --ios` | Scaffold native iOS shell in `mobile/ios/` |
| `spinx build` | Production build: compiled assets + primed backend cache |

---

## 🧪 Testing & Verification

Run the test suite across all subsystems:

```bash
php tests/Integration/KernelIntegrationTest.php
```

All 92 integration assertions across Validation, Scheduler, Auth, Sessions, Routing DSL, and DBAL QueryBuilder pass with 100% success rate.

---

## 📄 License

Spinx is open-sourced software licensed under the [MIT license](LICENSE).
