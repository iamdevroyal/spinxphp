# Spinx Framework Architecture & Builder Context Reference

**Target Version:** Spinx v1.0.17+  
**Architecture Paradigm:** Kernel-Enforced Domain-Driven Design (DDD)  
**Execution Runtime:** Persistent Process Workers (RoadRunner default, Swoole opt-in)

---

## 1. Core Architectural Pillars

1. **Strict DDD Module Boundaries:** Every application feature MUST live inside `app/Modules/<ModuleName>/`. Loose files in global root folders (e.g. `app/Models/`, `app/Http/Controllers/`, `routes/web.php`) are strictly prohibited.
2. **Persistent-Worker Isolation:** Spinx boots once per worker process. State MUST NEVER leak across requests through static global variables, un-reset singletons, or superglobals (`$_SESSION`, `$_GET`, `$_POST`).
3. **Facade & Utility Standard:** Use Spinx's native facades (`Request::`, `Response::`, `DB::`, `Auth::`, `Queue::`, `Broadcast::`, `Storage::`, `Vector::`, `Llm::`, `Cache::`, `Log::`, `Redis::`). NEVER import raw Symfony HttpFoundation classes or Laravel `Illuminate\*` packages.
4. **Bidirectional Contract Grounding:** Always verify contracts between frontend templates and backend controllers/routes. Never generate fabricated stub endpoints or mock data.

---

## 2. Directory & Module Anatomy

Every module in `app/Modules/<Name>/` has the following rigid anatomy:

```
app/Modules/<ModuleName>/
├── Domain/
│   ├── Entities/             -- Pure domain entities with typed properties & business mutation methods.
│   │                         -- ZERO framework, HTTP, DBAL, or Model imports!
│   ├── ValueObjects/         -- Immutable domain value objects (e.g. Money, Email, Address).
│   ├── Events/               -- Plain PHP domain event classes.
│   └── Repositories/         -- Repository interface contracts only (*Interface.php).
├── Application/
│   ├── Services/             -- Use-case orchestration services coordinating entities & repositories.
│   └── Jobs/                 -- Asynchronous queue jobs (implementing Spinx\Queue\Job).
├── Infrastructure/
│   ├── Http/
│   │   ├── Controllers/      -- Thin HTTP controllers using Spinx Request/Response facades.
│   │   └── Middleware/       -- Request middlewares (implementing MiddlewareInterface).
│   ├── Repositories/         -- Concrete DBAL 4 repository implementations of Domain interfaces.
│   ├── Persistence/
│   │   ├── Models/           -- Active Record models extending Spinx\Database\Model.
│   │   └── Migrations/       -- Timestamped migrations using Spinx\Database\Schema\Blueprint.
│   └── Views/                -- Template views (*.spinx.html).
└── module.php                -- Declarative routing, alias registry, and DI container wiring.
```

---

## 3. Layer Implementation Rules & Code Blueprints

### A. Domain Entities (`Domain/Entities/<Entity>.php`)
- Pure PHP 8.2+ class with typed properties and business methods.
- **Rule:** NO `use Spinx\...` (except Support ValueObjects if pure), NO `use Symfony\...`, NO `use Doctrine\...`.
```php
<?php

declare(strict_types=1);

namespace App\Modules\Billing\Domain\Entities;

final class Invoice
{
    public function __construct(
        private readonly int $id,
        private int $customerId,
        private float $amount,
        private string $status = 'pending',
    ) {
    }

    public function getId(): int { return $this->id; }
    public function getCustomerId(): int { return $this->customerId; }
    public function getAmount(): float { return $this->amount; }
    public function getStatus(): string { return $this->status; }

    public function markAsPaid(): void
    {
        if ($this->status === 'paid') {
            throw new \DomainException('Invoice is already paid.');
        }
        $this->status = 'paid';
    }
}
```

### B. Repository Interfaces (`Domain/Repositories/<Entity>RepositoryInterface.php`)
```php
<?php

declare(strict_types=1);

namespace App\Modules\Billing\Domain\Repositories;

use App\Modules\Billing\Domain\Entities\Invoice;

interface InvoiceRepositoryInterface
{
    public function findById(int $id): ?Invoice;
    public function save(Invoice $invoice): void;
    public function delete(int $id): void;
}
```

### C. Application Services (`Application/Services/<Name>Service.php`)
- Coordinates domain use-cases, transactions, queue jobs, and broadcast events.
```php
<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application\Services;

use App\Modules\Billing\Domain\Repositories\InvoiceRepositoryInterface;
use Spinx\Database\DB;
use Spinx\Queue\Queue;
use Spinx\Broadcasting\Broadcast;

final class ProcessPaymentService
{
    public function __construct(
        private readonly InvoiceRepositoryInterface $invoices,
    ) {
    }

    public function handle(int $invoiceId): void
    {
        DB::transaction(function () use ($invoiceId) {
            $invoice = $this->invoices->findById($invoiceId);
            if ($invoice === null) {
                throw new \InvalidArgumentException('Invoice not found.');
            }

            $invoice->markAsPaid();
            $this->invoices->save($invoice);
        });

        // Async job and real-time broadcast
        Queue::onQueue('billing')->withPriority(10)->push(new \App\Modules\Billing\Application\Jobs\GenerateReceiptJob($invoiceId));
        Broadcast::private('invoices.' . $invoiceId)->event('InvoiceSettled', ['id' => $invoiceId, 'status' => 'paid']);
    }
}
```

### D. Active Record Models (`Infrastructure/Persistence/Models/<Model>.php`)
```php
<?php

declare(strict_types=1);

namespace App\Modules\Billing\Infrastructure\Persistence\Models;

use Spinx\Database\Model;

final class InvoiceModel extends Model
{
    protected static string $table = 'invoices';
    protected array $fillable = ['customer_id', 'amount', 'status'];
    protected array $casts = ['amount' => 'float', 'customer_id' => 'int'];
}
```

### E. Database Migrations (`Infrastructure/Persistence/Migrations/<Timestamp>_<Name>.php`)
```php
<?php

declare(strict_types=1);

use Spinx\Database\Migration;
use Spinx\Database\Schema\Blueprint;
use Spinx\Database\Schema\SchemaBuilder;

return new class extends Migration {
    public function up(SchemaBuilder $schema): void
    {
        // Enable extensions if PostgreSQL (e.g. pgvector)
        $schema->enableExtension('vector');

        $schema->create('invoices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('customer_id');
            $table->decimal('amount', 10, 2);
            $table->string('status', 32);
            $table->vector('embedding', 1536); // Semantic search support
            $table->timestamps();
            $table->index('status');
        });
    }

    public function down(SchemaBuilder $schema): void
    {
        $schema->dropIfExists('invoices');
    }
};
```

### F. HTTP Controllers (`Infrastructure/Http/Controllers/<Name>Controller.php`)
```php
<?php

declare(strict_types=1);

namespace App\Modules\Billing\Infrastructure\Http\Controllers;

use App\Modules\Billing\Application\Services\ProcessPaymentService;
use Spinx\Http\Request;
use Spinx\Http\Response;

final class InvoiceController
{
    public function __construct(
        private readonly ProcessPaymentService $paymentService,
    ) {
    }

    public function pay(int $id): Response
    {
        $validated = Request::validate([
            'payment_method' => 'required|string',
        ]);

        $this->paymentService->handle($id);

        return Response::jsonSuccess([
            'message'    => 'Payment initiated successfully',
            'invoice_id' => $id,
        ]);
    }
}
```

### G. Module Manifest (`module.php`)
```php
<?php

declare(strict_types=1);

use App\Modules\Billing\Infrastructure\Http\Controllers\InvoiceController;
use App\Modules\Billing\Domain\Repositories\InvoiceRepositoryInterface;
use App\Modules\Billing\Infrastructure\Repositories\InvoiceRepository;
use Spinx\Routing\AliasRegistry;
use Spinx\Routing\Route;
use Spinx\Routing\RouteBuilder;
use Symfony\Component\DependencyInjection\ContainerBuilder;

return [
    'controllers' => static function (AliasRegistry $r): void {
        $r->registerController('invoice_controller', InvoiceController::class);
    },

    'middlewares' => static function (AliasRegistry $r): void {
    },

    'routes' => static function (RouteBuilder $routes): void {
        Route::group('/api/v1/invoices', function () {
            Route::post(['invoices.pay', '/{id}/pay'])
                ->controller('invoice_controller@pay');

            // Webhooks exempt from CSRF
            Route::post(['invoices.webhook', '/webhook'])
                ->withoutCsrf()
                ->controller('invoice_controller@webhook');
        });
    },

    'services' => static function (ContainerBuilder $container, string $moduleDir): void {
        $container->register(InvoiceRepositoryInterface::class, InvoiceRepository::class)
            ->setAutowired(true)
            ->setPublic(true);
    },
];
```

---

## 4. Spinx Core Subsystem Standard APIs

| Subsystem | Facade / Class | Key Usage |
|---|---|---|
| **HTTP Request** | `Spinx\Http\Request` | `Request::all()`, `Request::input('key')`, `Request::validate([...])`, `Request::rawBody()`, `Request::bearerToken()` |
| **HTTP Response** | `Spinx\Http\Response` | `Response::json($data, 200)`, `Response::jsonSuccess($data)`, `Response::jsonError($msg, 400)`, `Response::redirect('/url')` |
| **Database** | `Spinx\Database\DB` | `DB::transaction(fn($conn) => ...)`, `DB::select('SELECT...')`, `DB::selectOne(...)`, `DB::statement(...)` |
| **Active Record** | `Spinx\Database\Model` | `Model::create([...])`, `Model::find($id)`, `Model::query()->where(...)->get()` |
| **Queue** | `Spinx\Queue\Queue` | `Queue::push(new Job())`, `Queue::onQueue('high')->withPriority(10)->push($job)`, `Queue::later(60, $job)` |
| **Broadcasting** | `Spinx\Broadcasting\Broadcast` | `Broadcast::channel('orders')->event('NewOrder', $payload)`, `Broadcast::private('user.1')->event(...)` |
| **Storage / Files**| `Spinx\Filesystem\Storage` | `Storage::disk('s3')->put($path, $bytes)`, `Storage::get($path)`, `Storage::temporaryUrl($path, $time)` |
| **Vector Search** | `Spinx\Database\Vector\Vector`| `Vector::embed($text)`, `Vector::search('table', 'embedding', $queryVector, ['status' => 'active'], 10)` |
| **Application LLM**| `Spinx\Llm\Llm` | `Llm::chat('prompt')`, `Llm::provider('openai')->generate($request)` |
| **Cache** | `Spinx\Cache\Cache` | `Cache::remember('key', 3600, fn() => ...)`, `Cache::put('key', $val, 600)`, `Cache::get('key')` |
| **Logging** | `Spinx\Log\Log` | `Log::info($msg, $context)`, `Log::error($msg, ['exception' => $e])` |
| **Redis** | `Spinx\Redis\Redis` | `Redis::connection('cache')->get($key)`, `Redis::setex($k, $ttl, $v)` |
| **Auth** | `Spinx\Auth\Auth` | `Auth::attempt(['email' => $e, 'password' => $p])`, `Auth::check()`, `Auth::user()`, `Auth::id()` |
| **Webhooks** | `Spinx\Http\Webhook\HmacWebhookVerifier` | `(new HmacWebhookVerifier())->verify(Request::rawBody(), $sigHeader, $secret)` |

---

## 5. Explicit Anti-Patterns & Prohibitions (STRICT)

When designing, generating, or reviewing code, the Spinx AI Builder MUST NEVER:
1. ❌ **Create global non-DDD folders:** Never create `app/Models/`, `app/Http/Controllers/`, or global `database/migrations/`. Everything belongs in `app/Modules/<Name>/`.
2. ❌ **Create global route files:** Never create or reference `routes/web.php` or `routes/api.php`. Routes belong in `app/Modules/<Name>/module.php`.
3. ❌ **Use superglobals:** Never use `$_SESSION`, `$_GET`, `$_POST`, `$_SERVER`, `$_FILES` (they leak across persistent worker requests). Always use `Request::` and `SessionInterface`.
4. ❌ **Import raw Symfony HTTP classes in Controllers:** Never use `use Symfony\Component\HttpFoundation\Response;` in module controllers. Use `use Spinx\Http\Response;` and `use Spinx\Http\Request;`.
5. ❌ **Pollute Domain Entities:** Never import DBAL, Eloquent, SQL, or HTTP classes into `Domain/Entities/`.
6. ❌ **Import Laravel `Illuminate\*` packages:** Spinx is an independent persistent-worker framework. Never suggest Artisan commands or `Illuminate` classes.
7. ❌ **Create Mock Dummy Endpoints:** Never invent fake ungrounded APIs. Always verify contract alignment between frontend views and backend controllers.
