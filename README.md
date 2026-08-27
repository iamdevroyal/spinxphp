<div align="center">

# Spinx Framework

**The Modern High-Performance PHP Framework for Persistent Workers, Enforced DDD Architecture, Universal Queues, Real-Time WebSockets, and Autonomous AI Generation.**

[![Latest Version](https://img.shields.io/badge/release-v1.0.17-6366f1.svg?style=flat-square)](https://github.com/iamdevroyal/spinxphp)
[![Documentation](https://img.shields.io/badge/docs-spinxphp.pages.dev%2Fdocs-ec4899.svg?style=flat-square)](https://spinxphp.pages.dev/docs)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.2-8b5cf6.svg?style=flat-square)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)](LICENSE)
[![Build Status](https://img.shields.io/badge/tests-69%2F69%20passing-10b981.svg?style=flat-square)](https://github.com/iamdevroyal/spinxphp)

</div>

---

## ⚡ Why Spinx?

Traditional PHP frameworks run on PHP-FPM, destroying and recreating the application lifecycle on every incoming HTTP request. Spinx runs inside **long-lived persistent execution workers** (RoadRunner by default, Swoole coroutines opt-in). Route compilation, dependency injection reflection, configuration parsing, and database schemas remain warmed in RAM across requests — delivering **sub-millisecond response latencies and handling thousands of requests per second per node**.

Spinx pairs extreme execution speed with **Kernel-Enforced Domain-Driven Design (DDD)**, an autonomous **AI Projects Builder**, and full native support for **Asynchronous Queues**, **Real-Time WebSockets**, **Multi-Disk Cloud Storage**, and **Semantic Vector Search (`pgvector`)**.

---

## 🚀 Core Pillars & Subsystems

| Subsystem | Key Primitives | Description |
|---|---|---|
| **⚡ Persistent Runtime** | RoadRunner, Swoole | High-throughput coroutine/worker execution with zero per-request bootstrap cost. |
| **🏛️ Enforced DDD** | `app/Modules/<Name>/` | Pure Domain Entities, Repository Contracts, Application Services, and Infrastructure separation. |
| **🛡️ Zero-Leak Safety** | `RequestScope`, `Csrf::reset()` | Request isolation and automatic garbage collection between persistent worker requests. |
| **⏳ Universal Queues** | `Queue::`, `Job`, `Worker` | Priority queues (`withPriority()`), delayed jobs (`later()`), retry backoffs, and HMAC anti-tampering. |
| **📡 Real-Time WebSockets** | `Broadcast::`, `ShouldBroadcast` | Pusher protocol driver (100% compatible with **Soketi**, **Pusher Cloud**, **Laravel Reverb**) and private channels. |
| **📦 Multi-Disk Storage** | `Storage::disk('s3')` | Native AWS Signature V4 supporting **AWS S3**, **Cloudflare R2**, **MinIO**, and temporary signed URLs. |
| **🧠 Vector Search** | `Vector::`, `pgvector` | Semantic embedding generation and cosine/Euclidean vector search for AI-native applications. |
| **🤖 Application LLM** | `Llm::chat()` | Generic AI layer supporting **Anthropic** and **OpenAI** with structured request/response DTOs. |
| **🔴 Centralized Redis** | `Redis::`, `RedisSession` | Multi-connection database pooling (`cache`, `session`, `queue`) and atomic distributed rate limiting. |
| **🔒 Production Security** | `HmacWebhookVerifier` | Cryptographic raw body webhook verification, `Route::withoutCsrf()`, and strict CORS matching. |
| **🤖 Autonomous AI Builder**| `Spinx\Ai`, `Orchestrator` | 9-Agent autonomous engineering fleet guided by kernel-enforced `SPINX_AI_ARCHITECTURE.md` context. |
| **🏝️ Reactive Islands** | `@island`, `@csrf` | Native server-rendered views (`*.spinx.html`) with selective Vue 3 & React 19 client-side hydration. |

---

## 📦 Installation & Quickstart

### Recommended — Global Installer

Install the official Spinx installer globally once:

```bash
composer global require spinxphp/installer
```

Then create new projects from anywhere:

```bash
spinx new my-app
```

An interactive wizard configures your frontend, database, runtime, and URL. When done:

```bash
cd my-app
php spinx serve
```

**Options:**

```bash
spinx new my-app --frontend=vue              # Vue 3 + Vite (default)
spinx new my-app --frontend=react            # React 19 + Vite
spinx new my-app --frontend=none             # API-only (no frontend)
spinx new my-app --version=1.0.0             # Specific framework version
spinx new my-app --frontend=vue -n           # Non-interactive (CI/CD)
```

---

### Alternative — Direct Composer Install

Without the global installer, use `composer create-project` directly:

```bash
composer create-project spinx/spinx my-spinx --stability=dev
cd my-spinx
```

### Step 2 — Scaffold a New Application

```bash
# Scaffold a new project directory (choose vue or react frontend)
php spinx new my-app --frontend=vue
cd my-app
```

### Step 3 — Install All Dependencies

```bash
# Install PHP dependencies
composer install

# Install Node.js/Vite frontend dependencies
cd frontend && npm install && cd ..
```

### Step 4 — Download the RoadRunner Binary

Spinx uses [RoadRunner](https://roadrunner.dev) as its default persistent HTTP worker. Download the matching binary for your OS:

```bash
vendor/bin/rr get
```

> **Swoole users:** Set `"driver": "swoole"` in `spinx.json` and skip this step.
> You can also swap at any time via: `php spinx driver:swap swoole`

### Step 5 — Configure Your Environment

```bash
cp .env.example .env

# Edit .env and set at minimum:
# APP_KEY=base64:...    (generate a strong 32-byte key)
# DB_CONNECTION=pgsql   (or sqlite for local dev)
# REDIS_HOST=127.0.0.1  (if using queues, sessions, or cache)
```

### Step 6 — Run Database Migrations

```bash
php spinx migrate
```

### Step 7 — Start the Development Server

```bash
# Starts RoadRunner (or Swoole) + Vite HMR server concurrently
php spinx serve
```

Your application is now running at `http://localhost:8080` and the Vite HMR dev server is live at `http://localhost:5173`.

---

### System Requirements

| Requirement | Minimum Version | Notes |
|---|---|---|
| **PHP** | `>= 8.2` | Typed properties, readonly, enums, fibers |
| **Extensions** | `mbstring`, `pdo`, `json` | `pdo_pgsql` for PostgreSQL, `pdo_mysql` for MySQL, `redis` for Redis |
| **Composer** | `>= 2.0` | Dependency management |
| **Node.js** | `>= 18.0` | Required only for Vite frontend pipeline |
| **RoadRunner** | Latest | Auto-downloaded via `vendor/bin/rr get` |
| **PostgreSQL** | `>= 14` *(optional)* | Required for `pgvector` Vector Search |

---

### Optional Services (`.env` / `config/`)

| Feature | Env Variable | Default |
|---|---|---|
| **Redis Caching** | `REDIS_HOST` | Disabled (falls back to file) |
| **Redis Sessions** | `SESSION_DRIVER=redis` | File driver |
| **Redis Queues** | `QUEUE_CONNECTION=redis` | Database driver |
| **WebSockets (Soketi)** | `BROADCAST_DRIVER=pusher`, `PUSHER_HOST` | Null driver |
| **AWS S3 / R2** | `FILESYSTEM_DISK=s3`, `AWS_*` keys | Local disk |
| **OpenAI Embeddings** | `OPENAI_API_KEY` | Disabled |
| **Anthropic LLM** | `ANTHROPIC_API_KEY` | Disabled |

---

## 🧩 Enforced DDD Module Anatomy

Spinx eliminates messy root folders by enforcing Domain-Driven Design (DDD) boundaries at the kernel level:

```
app/Modules/<ModuleName>/
├── Domain/
│   ├── Entities/             -- Pure PHP entities with typed properties & business mutations.
│   │                         -- ZERO framework, HTTP, DBAL, or Model imports!
│   ├── ValueObjects/         -- Immutable domain value objects (e.g. Money, Email, Address).
│   ├── Events/               -- Domain events.
│   └── Repositories/         -- Repository interface contracts only (*Interface.php).
├── Application/
│   ├── Services/             -- Use-case orchestration services.
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

## 💡 Code Examples

### 1. Asynchronous Queue Processing (`Queue::`)
```php
use Spinx\Queue\Queue;
use App\Modules\Billing\Application\Jobs\ProcessPaymentJob;

// Push to high priority queue
Queue::onQueue('billing')
    ->withPriority(10)
    ->push(new ProcessPaymentJob($invoiceId));

// Delay execution by 60 seconds
Queue::later(60, new ProcessPaymentJob($invoiceId));
```

### 2. Real-Time WebSocket Event Broadcasting (`Broadcast::`)
```php
use Spinx\Broadcasting\Broadcast;
use Spinx\Broadcasting\PrivateChannel;
use Spinx\Broadcasting\ShouldBroadcast;

// Event implementing ShouldBroadcast
class InvoicePaidEvent implements ShouldBroadcast
{
    public function __construct(public int $invoiceId, public float $amount) {}

    public function broadcastOn(): PrivateChannel {
        return new PrivateChannel('invoices.' . $this->invoiceId);
    }

    public function broadcastWith(): array {
        return ['id' => $this->invoiceId, 'status' => 'paid', 'amount' => $this->amount];
    }
}

// Dispatch event to WebSocket subscribers (Soketi / Pusher / Reverb)
Broadcast::event(new InvoicePaidEvent(42, 199.99));
```

### 3. Multi-Disk Cloud Storage (`Storage::`)
```php
use Spinx\Filesystem\Storage;

// Store to Cloudflare R2 / AWS S3
Storage::disk('s3')->put('reports/annual_2026.pdf', $pdfBytes);

// Generate secure temporary signed download URL (valid for 2 hours)
$url = Storage::disk('s3')->temporaryUrl('reports/annual_2026.pdf', now()->addHours(2));
```

### 4. Semantic Vector Search (`Vector::`)
```php
use Spinx\Database\Vector\Vector;

// 1. Generate text embedding
$embedding = Vector::embed('Artificial intelligence agent architecture');

// 2. Perform cosine similarity search over database table
$results = Vector::search(
    table: 'documents',
    vectorColumn: 'embedding',
    queryVector: $embedding,
    filters: ['status' => 'published'],
    limit: 5,
    metric: 'cosine' // cosine (<=>), l2 (<->), inner_product (<#>)
);
```

### 5. Application AI & LLM Bridge (`Llm::`)
```php
use Spinx\Llm\Llm;
use Spinx\Llm\ChatMessage;
use Spinx\Llm\LlmRequest;

// Quick chat
$reply = Llm::chat('Explain persistent-process PHP runtimes in two sentences.');

// Structured JSON generation
$response = Llm::provider('anthropic')->generate(
    (new LlmRequest())
        ->setSystemPrompt('You output strictly valid JSON.')
        ->addUserMessage('Generate a user profile for John Doe.')
);

$userData = $response->json();
```

---

## 🤖 Spinx AI Builder & 9-Agent Fleet

Spinx includes an autonomous engineering AI subsystem located in `Spinx\Ai`. The builder coordinates 9 specialized agents that autonomously design, generate, and audit full DDD application modules:

```
                             ┌─────────────────────────┐
                             │    OrchestratorAgent    │
                             └────────────┬────────────┘
                                          │
    ┌──────────────┬──────────────┬───────┴──────┬──────────────┬──────────────┬──────────────┬──────────────┐
┌───▼────┐   ┌─────▼────┐   ┌─────▼────┐   ┌─────▼────┐   ┌─────▼────┐   ┌─────▼────┐   ┌─────▼────┐   ┌─────▼────┐
│Architect│  │ Database │   │ Routing  │   │ Frontend │   │ Security │   │  DevOps  │   │  Async   │   │ Storage  │
│ Agent  │   │  Agent   │   │  Agent   │   │  Agent   │   │  Agent   │   │  Agent   │   │  Agent   │   │  Vector  │
└────────┘   └──────────┘   └──────────┘   └──────────┘   └──────────┘   └──────────┘   └──────────┘   └──────────┘
```

- **Enforced Invariants:** Guided by [`resources/ai/SPINX_AI_ARCHITECTURE.md`](resources/ai/SPINX_AI_ARCHITECTURE.md).
- **Proactive Anti-Pattern Guard:** `AiGuard::detectArchitecturalViolations()` detects non-Spinx requests (e.g. asking for `app/Models` or `routes/web.php`) and guides developers into Spinx DDD conventions.
- **Production Readiness Audit:** Every build is checked for syntax, DDD purity, and security before finishing.

---

## 🛠️ CLI Reference

```bash
# Development & Runtime
spinx serve                       # Start persistent worker runtime + Vite HMR server
spinx preview --mobile            # Launch browser mobile viewport preview container

# Domain-Driven Code Generation
spinx make:module <Name>          # Scaffold complete DDD module structure
spinx make:migration <Name>       # Create timestamped schema migration
spinx make:model <Name>           # Create DBAL Active Record model
spinx make:controller <Name>      # Create HTTP controller

# Database & Schema
spinx migrate                     # Execute pending database migrations
spinx migrate:fresh               # Drop all tables and re-run all migrations
spinx schema:compile              # Compile table schema columns into ahead-of-time cache

# Queues & Background Workers
spinx queue:work                  # Start queue worker daemon
spinx queue:work --queue=high,def # Poll priority queues in order

# Autonomous AI Builder
spinx ai:build "<prompt>"         # Execute autonomous multi-agent feature build
spinx ai:chat "<prompt>"          # Interactive consultation with OrchestratorAgent
```

---

## 📄 License

Spinx is open-sourced software licensed under the [MIT License](LICENSE).
