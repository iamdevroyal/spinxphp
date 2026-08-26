# Spinx Inbuilt AI Framework Builder

Spinx includes an **inbuilt, multi-agent AI Framework Builder** powered by Anthropic's Claude API (default: **Claude Sonnet 4.6**). The AI Builder is integrated directly into the framework kernel and understands Spinx's module structure, strict Domain-Driven Design (DDD) rules, database migrations, routing DSL, facades, and persistent runtime lifecycle.

---

## 1. Quick Start

### 1.1 Configure API Key
Add your Anthropic API key to `.env`:
```env
ANTHROPIC_API_KEY=sk-ant-api03-...
ANTHROPIC_MODEL=claude-sonnet-4-6
```

Or configure via `config/ai.php`:
```php
return [
    'default' => 'anthropic',
    'providers' => [
        'anthropic' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'model'   => env('ANTHROPIC_MODEL', 'claude-sonnet-4-6'),
            'max_tokens' => 8192,
        ],
    ],
];
```

---

## 2. Interactive Interfaces

### 2.1 Terminal Chat Mode
Run `spinx ai:chat` to launch an interactive, conversational AI pair programmer in your terminal:
```bash
php spinx ai:chat
```

### 2.2 Autonomous One-Shot Build
Run `spinx ai:build` to have the multi-agent system scaffold an entire production-grade module:
```bash
php spinx ai:build "Create a Billing module with Customer entity, Subscription plan repository, Stripe webhook handler, and checkout views"
```

### 2.3 Local Web Builder Studio
Navigate to `http://localhost:8080/_spinx/ai` or run:
```bash
php spinx ai:ui
```
This opens the visual AI Development Studio in your browser, featuring:
* Real-time streaming code generation
* Visual diff reviews before saving to disk
* Direct execution of Spinx CLI commands
* Architectural DDD compliance checker

---

## 3. Specialized Multi-Agent Core

Spinx AI Builder employs a hierarchical multi-agent architecture where an **Orchestrator Agent** routes development tasks to specialized domain agents:

```
                  ┌──────────────────────┐
                  │  Orchestrator Agent  │
                  └──────────┬───────────┘
                             │
     ┌───────────────┬───────┴───────┬───────────────┐
     │               │               │               │
┌────▼─────┐   ┌─────▼────┐    ┌─────▼────┐    ┌─────▼────┐
│Architect │   │ Database │    │ Routing  │    │ Frontend │
│  Agent   │   │  Agent   │    │  Agent   │    │  Agent   │
└──────────┘   └──────────┘    └──────────┘    └──────────┘
 (DDD Core)     (Migrations)    (Controllers)   (Templates)
     │               │               │               │
     └───────────────┼───────────────┴───────────────┘
                     │
     ┌───────────────┴───────────────┐
     │                               │
┌────▼─────┐                   ┌─────▼────┐
│ Security │                   │  DevOps  │
│  Agent   │                   │  Agent   │
└──────────┘                   └──────────┘
 (Auth/CSRF)                    (Run/Cache)
```

1. **Orchestrator Agent:** Coordinates task execution, dependencies, and step-by-step verification.
2. **Architect Agent:** Formulates Domain Entities, Repository Contracts (`UserRepositoryInterface`), and Application Services (`AuthService`).
3. **Database Agent:** Generates timestamped migrations with `Blueprint`, DBAL models, and schema compilation.
4. **Routing Agent:** Designs multi-action controllers, routes in `module.php`, and `Request::validate()` rules.
5. **Frontend Agent:** Builds `.spinx.html` Blade templates, components, and `@island` reactive hydration widgets.
6. **Security Agent:** Implements session-backed CSRF protection, auth middleware guards, and rate limiting.
7. **DevOps Agent:** Configures runtime persistent workers (RoadRunner / Swoole), queue workers, and cache stores.

---

## 4. Continuity Tracker & Project Context

To ensure the AI never loses context across sessions, Spinx writes a persistent project memory snapshot to `.spinx/ai/continuity.json`:

```json
{
  "project": "SaaS Platform",
  "modules": ["Auth", "Billing", "Projects"],
  "database": {
    "tables": ["users", "plans", "subscriptions", "projects"]
  },
  "decisions": [
    "Used strict DDD architecture across all modules",
    "Password hashing standardized with Argon2id",
    "Session-backed CSRF enabled on all state-changing endpoints"
  ]
}
```

---

## 5. Programmatic Facade Usage

You can also invoke the AI builder programmatically inside your PHP scripts or custom tools:

```php
use Spinx\Ai\Ai;

// Chat with the Orchestrator
$response = Ai::chat('How should I model a multi-tenant subscription in Spinx?');

// Build a module programmatically
$result = Ai::build('Generate a Notifications module with database and mail channels');

// Access a specialized agent directly
$architect = Ai::agent('architect');
$plan = $architect->analyzeModule('Billing');
```
