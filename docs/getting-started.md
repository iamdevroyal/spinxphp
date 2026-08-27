# Getting Started

## Quickstart (Recommended — Global Installer)

Install the Spinx global installer once:

```bash
composer global require spinxphp/installer
```

Then create any new project from anywhere:

```bash
spinx new my-app
```

An interactive wizard guides you through frontend, database, and runtime selection. Once complete:

```bash
cd my-app
php spinx serve
```

Visit `http://localhost:8080`.

**Frontend presets:**

```bash
spinx new my-app --frontend=vue     # Vue 3 + Vite (default)
spinx new my-app --frontend=react   # React 19 + Vite
spinx new my-app --frontend=none    # No frontend (API only)
```

**Non-interactive (CI/CD):**

```bash
spinx new my-app --frontend=vue --no-interaction
```

---

## Alternative: Direct Composer Install

You can also create a project without the global installer:

```bash
composer create-project spinxphp/framework my-app
```

Composer will automatically trigger Spinx's interactive CLI setup wizard, which guides you through:
1. **Application Name & URL configuration**
2. **Frontend Adapter Selection** (Vue 3 + Vite or React 19)
3. **Database Driver Setup** (SQLite zero-config, MySQL, or PostgreSQL)
4. **Runtime Driver Selection** (RoadRunner persistent workers or Swoole coroutines)
5. **Automatic RoadRunner Binary Download** (`vendor/bin/rr get` is executed automatically)
6. **Frontend Dependency Installation** (Runs `npm install` inside `frontend/`)
7. **Database Migrations** (Runs `php spinx migrate`)

Once the installer completes, simply start your development server:

```bash
cd my-app
php spinx serve
```

Visit:
- `http://localhost:8080/` — the Spinx Welcome Dashboard (diagnostics + reactive island demo)
- `http://localhost:8080/health` — JSON health check route

---

## Development Setup (Cloning from Source)

If you are contributing to the framework or cloning the source repository directly:

```bash
composer install
cd frontend && npm install && cd ..
vendor/bin/rr get
php spinx migrate
php spinx serve
```

In the source repository, you can also explore reference modules:
- `http://localhost:8080/todos` — raw HTML reference module (zero JavaScript framework)
- For the React reference implementation, see [templating.md](templating.md#reference-implementation-2-react).

---

## Your First Module

Spinx strictly enforces Domain-Driven Design (DDD) boundaries. Scaffold a complete module in seconds:

```bash
php spinx make:module Orders
php spinx make:entity Orders Order
php spinx make:model Orders Order
php spinx make:repository Orders Order
php spinx make:service Orders PlaceOrder
php spinx make:controller Orders ListOrders
php spinx make:migration Orders create_orders_table
```

Or generate all layers at once:

```bash
php spinx make:module Orders --all
```

Then run pending migrations:

```bash
php spinx migrate
```

---

## Where Things Live

```
app/Modules/<Name>/          Your application code — see Architecture
config/                      Application configuration files (.env backed)
config/container.php          Framework-level DI definitions only
resources/views/             Global .spinx.html templates
frontend/                    Vue (default) frontend + reactive islands
examples/react-frontend/     React alternative — see templating.md
tools/mobile-preview/        Interactive mobile preview container
storage/                     Compiled caches, session files, SQLite DB
```

> **Note:** The `tools/desktop-preview/` native Go shell and mobile bridge are available in the source repository for native packaging spikes.

Next: Read [Architecture](architecture.md) to understand how `app/Modules` works and why request scoping prevents state leaks on persistent workers.
