# Spinx CLI Reference

The `spinx` binary provides a complete suite of commands for project scaffolding, runtime booting, migrations, caching, optimization, and autonomous AI building.

---

## 1. Development & Runtime Commands

| Command | Description |
|---|---|
| `spinx new <project>` | Scaffold a brand new Spinx project with Vue or React |
| `spinx serve` | Boot the persistent server (RoadRunner/Swoole) + Vite dev server |
| `spinx driver:swap <driver>` | Switch runtime driver between `roadrunner` and `swoole` |
| `spinx preview --mobile` | Open dev server in responsive mobile device preview |
| `spinx preview --desktop` | Open dev server in native desktop webview window |
| `spinx logs [--lines=N]` | View recent application logs with colored trace formatting |
| `spinx log:clear` | Clear all log files in `storage/logs/` |

---

## 2. Inbuilt AI Framework Builder Commands

| Command | Description |
|---|---|
| `spinx ai:chat` | Launch interactive terminal AI pair programmer (Claude Sonnet 4.6) |
| `spinx ai:build "<prompt>"` | Autonomous one-shot module generator with strict DDD enforcement |
| `spinx ai:ui` | Open local Web AI Builder Dashboard at `http://localhost:8080/_spinx/ai` |

---

## 3. Cache & Optimization Commands

| Command | Description |
|---|---|
| `spinx optimize` | Pre-compile DI container, DBAL schema cache, and warm production cache |
| `spinx optimize:clear` | Clear all cached bootstrap files, schema, views, and application data |
| `spinx cache:clear` | Clear application data cache (`storage/cache/data/`) |
| `spinx cache:forget <key>` | Remove a specific key from application data cache |
| `spinx view:clear` | Clear compiled Blade view templates (`storage/cache/views/`) |
| `spinx container:clear` | Clear compiled DI container cache (`storage/cache/container.php*`) |
| `spinx schema:clear` | Clear compiled DBAL schema cache (`storage/cache/schema_columns.php`) |

---

## 4. Scaffolding & Database Commands

| Command | Description |
|---|---|
| `spinx make:module <Name> [--all]` | Scaffold a DDD module directory with domain/app/infra |
| `spinx make:controller <Mod> <Name>` | Generate a multi-action controller in module |
| `spinx make:entity <Mod> <Name>` | Generate a pure Domain entity |
| `spinx make:service <Mod> <Name>` | Generate an Application service |
| `spinx make:repository <Mod> <Name>` | Generate a repository interface + implementation pair |
| `spinx make:migration <Mod> <desc>` | Generate a timestamp-prefixed migration file |
| `spinx migrate [Name]` | Run pending database migrations |
| `spinx schema:compile` | Introspect database schema and write `storage/cache/schema_columns.php` |
| `spinx queue:work` | Poll and process the database-backed job queue |
| `spinx schedule:run` | Run scheduled tasks due in `schedule.php` |
| `spinx openapi:generate` | Generate OpenAPI 3.1 schema from routes and controller attributes |
