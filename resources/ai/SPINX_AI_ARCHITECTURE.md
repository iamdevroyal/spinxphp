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
| **Auth (Session)** | `Spinx\Auth\Auth` | `Auth::attempt(['email' => $e, 'password' => $p])`, `Auth::check()`, `Auth::user()`, `Auth::id()`, `Auth::logout()` |
| **API Tokens (PAT)** | `Spinx\Auth\Token\Token` | `Token::createToken($user, 'device', ['*'])`, `Token::findToken($rawBearer)`, `Token::revokeAll($user)` |
| **JWT Auth** | `Spinx\Auth\Jwt\Jwt` | `Jwt::encode($user, 3600, ['role' => 'admin'])`, `Jwt::decode($token)`, `Jwt::tryDecode($token)`, `Jwt::createRefreshToken($user)` |
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
8. ❌ **Write raw PHP in templates:** In `.spinx.html` always use `{{ $var }}`, `@class([...])`, `@loop`, `@error`, `@auth`, etc. Never write `<?= ?>` or raw `<?php echo ?>` blocks.
9. ❌ **Expose PAT plaintext twice:** The `NewAccessToken::$plainTextToken` must ONLY be returned once in the creation API response. Never re-read from DB or log it.

---

## 6. Spinx Directives Reference (v1.0.21+)

The FrontendAgent MUST use Spinx Directives in all `.spinx.html` templates. Raw PHP echo and ternary concatenation are prohibited.

### Layout & Composition
```html
@layout('Shared::app', ['title' => 'My Page'])
  @slot('sidebar') <nav>...</nav> @endslot
  <main>Page content</main>
  @push('scripts') <script src="..."></script> @endpush
@endlayout

@stack('scripts')          <!-- Output all pushed script blocks -->
@renderSlot('sidebar', 'Default nav')    <!-- Slot with fallback -->
@once ... @endonce         <!-- Render block exactly once per page -->
```

### Dynamic Styling
```html
<div @class(['card', 'card-active' => $isActive, 'opacity-50' => $isLocked])>
<div @style(['color:' . $color => !empty($color), 'display:none' => $isHidden])>
@css <style>.glass { backdrop-filter: blur(12px); }</style> @endcss
```

### Forms, CSRF & Security
```html
<form method="POST">
    @csrf                                          <!-- Hidden CSRF input -->
    @method('PUT')                                 <!-- Method spoofing -->
    @honeypot                                      <!-- Anti-bot fields -->
    <input value="{{ @old('email', $email) }}">    <!-- Old input restore -->
    <input @checked($isActive) @required(true)>   <!-- Boolean attr flags -->
    <select><option @selected($v === $cur)>Option</option></select>
    <button @disabled($isLocked)>Submit</button>
</form>
```

### Smart Loops (prefer @loop over @foreach for collections)
```html
@loop($items as $item)
    <tr @class(['odd' => $loop->odd])>
        <td>{{ $loop->iteration }}/{{ $loop->count }}: {{ $item->title }}</td>
    </tr>
@empty
    <tr><td colspan="3">No items found.</td></tr>
@endloop
```
`$loop`: `->first`, `->last`, `->index`, `->iteration`, `->count`, `->even`, `->odd`, `->remaining`, `->depth`.

### Auth & Permissions
```html
@auth ... @else ... @endauth    <!-- Logged-in check -->
@guest ... @endguest             <!-- Guest check -->
@role('admin') ... @endrole     <!-- Role check -->
@can('edit', $post) ... @endcan  <!-- Policy check -->
```

### Errors & Flash
```html
@error('email') <p class="error">{{ $message }}</p> @enderror
@hasErrors <div class="alert-box">Validation failed.</div> @endhasErrors
@flash('success') <div class="toast">{{ $message }}</div> @endflash
@flashAny <div class="alert alert-{{ $type }}">{{ $message }}</div> @endflashAny
```

### SEO, Media & Formatting
```html
@seo(['title' => 'Page', 'description' => '...', 'image' => '/og.jpg'])
@svg('icons/star.svg', ['class' => 'w-5 h-5 text-yellow-400'])
@avatar($user, ['size' => 40])
@date($date, 'F j, Y') · @timeAgo($date)
@money(1999, 'USD') · @fileSize($bytes) · @plural($n, 'item') · @truncate($text, 150)
```

### JavaScript & Islands
```html
<script>const state = @js($data);</script>
@window('AppConfig', ['apiUrl' => '/api/v1', 'userId' => $user->id])
@island('ComponentName', ['prop' => $value])        <!-- Vue/React hydrated -->
@islandLazy('HeavyChart', ['data' => $stats])       <!-- Lazy on viewport -->
@broadcast('channel.' . $id, 'EventName')           <!-- WebSocket hook -->
@vite                                               <!-- Inject Vite assets -->
```

### Performance & Debug
```html
@cache('nav', 3600) <nav>...</nav> @endcache
@benchmark('agent-render') ... @endbenchmark
@dump($variable) · @dd($variable)
@dev <div class="debug-bar">...</div> @enddev
@production <script>/* prod-only analytics */</script> @endproduction
```

---

## 7. API Authentication Guide (v1.0.22+)

### Driver Selection
Configure in `config/auth.php`:
```php
'api' => [
    'driver'     => env('API_AUTH_DRIVER', 'token'), // 'token' | 'jwt'
    'jwt_secret' => env('JWT_SECRET', env('APP_KEY')),
    'jwt_algo'   => 'HS256',
    'jwt_ttl'    => 3600,
]
```

### Personal Access Tokens (driver='token')
```php
// User Model: add the trait
use Spinx\Auth\Traits\HasApiTokens;
final class User extends Model { use HasApiTokens; }

// ApiAuthController: issue token on login
$user     = Auth::user();
$newToken = $user->createToken('iPhone App', ['projects:read', 'chapters:write']);
return Response::json(['access_token' => $newToken->plainTextToken, 'token_type' => 'Bearer']);

// module.php: protect routes
$routes->group(['prefix' => '/api/v1', 'middleware' => ['auth:api']], function ($api) {
    $api->get('/user', [ApiUserController::class, 'profile']);
    $api->post('/projects', [ApiProjectController::class, 'create'])
        ->middleware('ability:projects:create');
});

// Controller: check abilities
if (!Auth::tokenCan('projects:create')) {
    return Response::json(['error' => 'Forbidden'], 403);
}
// Revoke on logout
$user->revokeCurrentToken();
```

### Stateless JWT (driver='jwt')
```php
use Spinx\Auth\Jwt\Jwt;
// Issue
$access  = Jwt::encode($user, 3600, ['role' => 'author']);
$refresh = Jwt::createRefreshToken($user);
// Decode (throws JwtException on failure)
$payload = Jwt::decode($access);
$userId  = $payload['sub'];
// Safe decode (returns null on failure)
$payload = Jwt::tryDecode($token);
// Routes use same 'auth:api' middleware — driver is auto-detected from JWT shape
```

### Headless / API-Only Mode
```php
// All controllers in API-only apps:
public function list(): Response
{
    $user    = Auth::user();                       // Set by auth:api middleware
    $data    = Request::validate(['page' => 'integer|min:1']);
    $results = $this->projectService->paginate($user->id, $data['page'] ?? 1);

    return Response::json([
        'status' => 'success',
        'data'   => $results,
    ]);
}

// config/cors.php — required for decoupled frontends:
return [
    'allowed_origins'   => [env('FRONTEND_URL', 'http://localhost:3000')],
    'allowed_methods'   => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
    'allowed_headers'   => ['Content-Type', 'Authorization', 'X-Requested-With'],
    'allow_credentials' => true,
];
```

