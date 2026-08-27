# Autonomous Spinx AI Builder & 9-Agent Fleet

Spinx includes a built-in autonomous software engineering AI subsystem — a fleet of 9 specialized agents that work collaboratively to design, generate, audit, and fix full DDD application features, guided strictly by Spinx framework architecture invariants.

---

## 🤖 The 9-Agent Engineering Fleet

```
                             ┌─────────────────────────┐
                             │    OrchestratorAgent    │   — Routes tasks to specialists
                             └────────────┬────────────┘
                                          │
    ┌──────────────┬──────────────┬───────┴──────┬──────────────┬──────────────┬──────────────┬──────────────┐
┌───▼────┐   ┌─────▼────┐   ┌─────▼────┐   ┌─────▼────┐   ┌─────▼────┐   ┌─────▼────┐   ┌─────▼────┐   ┌─────▼────┐
│Architect│  │ Database │   │ Routing  │   │ Frontend │   │ Security │   │  DevOps  │   │  Async   │   │ Storage  │
│ Agent  │   │  Agent   │   │  Agent   │   │  Agent   │   │  Agent   │   │  Agent   │   │  Agent   │   │  Vector  │
│        │   │          │   │          │   │          │   │          │   │          │   │          │   │  Agent   │
└────────┘   └──────────┘   └──────────┘   └──────────┘   └──────────┘   └──────────┘   └──────────┘   └──────────┘
```

| Agent | Responsibility |
|---|---|
| **OrchestratorAgent** | Routes prompts to the correct specialist; merges sub-results; delivers final output |
| **ArchitectAgent** | DDD module structure, Domain entities, Value Objects, Repository interfaces |
| **DatabaseAgent** | Migrations, DBAL 4 ORM models, queries, pgvector schema extensions |
| **RoutingAgent** | `module.php` route builders, controllers, middleware, request/response facades |
| **FrontendAgent** | Reactive island templates (`.spinx.html`), Vue 3 / React 19 components, `@island` hydration |
| **SecurityAgent** | Auth, CSRF, CORS config, webhook verification, production hardening |
| **DevOpsAgent** | RoadRunner/Swoole config, Dockerfile, queue worker supervisord, schema compilation |
| **AsyncAgent** | Priority Queues, delayed jobs, Worker daemon setup, Broadcast events, WebSocket channels |
| **StorageVectorAgent** | Multi-disk Storage config, S3/R2 drivers, Vector::embed/search, `pgvector` migrations |

---

## 🚀 Using the AI Builder

### Interactive Chat
```bash
php spinx ai:chat "How do I implement a billing module with invoices and subscriptions?"
```

### Autonomous Build
```bash
# Fully autonomous — generates files, routes, migrations, and wires everything
php spinx ai:build "Build a content management system module with posts, categories, tags, and full CRUD API"
```

### AI Dashboard (Dev Only)
```bash
php spinx ai:dashboard
# Opens browser UI at http://localhost:8080/_spinx/ai
# Disabled in production unless SPINX_AI_DASHBOARD_ENABLED=true
```

---

## 🛡️ Architectural Guardrails (AiGuard)

The AI builder enforces Spinx DDD conventions through the `AiGuard::detectArchitecturalViolations()` engine. If a developer prompt attempts to use non-Spinx patterns, the AI warns and redirects:

| Forbidden Pattern | Spinx Convention |
|---|---|
| `app/Models/*.php` | `app/Modules/<Name>/Infrastructure/Persistence/Models/*.php` |
| `routes/web.php` | `app/Modules/<Name>/module.php` → `RouteBuilder::` |
| `$_SESSION[...]` | `SessionInterface::get/set()` |
| `Illuminate\Database\Eloquent` | `Spinx\Database\Model` |
| `Route::get('/', fn() => ...)` | `RouteBuilder::get('/', ControllerClass::class)` |
| Global service providers | Module-scoped DI wiring in `module.php` |

The system prompt for every AI Builder session is pre-loaded with the canonical [`SPINX_AI_ARCHITECTURE.md`](../resources/ai/SPINX_AI_ARCHITECTURE.md) context file — ensuring the AI always generates correct Spinx DDD code, not Laravel/Symfony hybrid patterns.

---

## 🔧 Configuring the AI Builder (`config/ai.php`)

```php
return [
    'default_provider' => env('AI_DEFAULT_PROVIDER', 'anthropic'),

    'providers' => [
        'anthropic' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'model'   => env('AI_BUILDER_MODEL', 'claude-3-7-sonnet-20250219'),
        ],
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model'   => env('OPENAI_BUILDER_MODEL', 'gpt-4o'),
        ],
    ],

    'tools' => [
        'spinx_command'         => true,  // Allow AI to run spinx make:* commands
        'architecture_validator'=> true,  // Validate generated code against DDD rules
        'production_readiness'  => true,  // Audit for security issues and anti-patterns
    ],
];
```

---

## 🔒 Production Safety

- AI dashboard routes (`/_spinx/ai/*`) are disabled automatically in `APP_ENV=production`.
- Enable explicitly only when behind authentication: `SPINX_AI_DASHBOARD_ENABLED=true`.
- `SpinxCommandTool` only allows a strict allowlist of safe `spinx make:*` commands — shell injection is prevented via per-argument `escapeshellarg()`.
