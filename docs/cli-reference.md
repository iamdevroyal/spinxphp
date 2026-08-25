# CLI Reference

## New Project

| Command | Description |
|---|---|
| `spinx new <project> [--frontend=vue\|react]` | Scaffold a brand new Spinx project |

## Project & Modules

| Command | Description |
|---|---|
| `spinx make:module <Name> [--all] [--except=x,y]` | Scaffold a DDD module — `--all` also generates entity/model/repository/service/controller/migration |
| `spinx make:controller <Module> <Name>` | Controller in `Infrastructure/Http/Controllers` |
| `spinx make:entity <Module> <Name>` | Domain entity (no persistence awareness) |
| `spinx make:service <Module> <Name>` | Application service |
| `spinx make:repository <Module> <Name>` | Domain interface + Infrastructure implementation |
| `spinx make:model <Module> <Name>` | ORM model in `Infrastructure/Persistence/Models` |
| `spinx make:middleware <Module> <Name>` | Middleware class in `Infrastructure/Http/Middleware` |
| `spinx make:migration <Module> <desc>` | Timestamp-prefixed migration file |
| `spinx make:mail <Module> <Name>` | Mailable + view + queueable Job |

## Database, Queues, Scheduler & Schema

| Command | Description |
|---|---|
| `spinx migrate [Name]` | Run pending migrations (all modules, or one if named) |
| `spinx module:migrate <Name>` | Run pending migrations for one module |
| `spinx schema:compile` | Introspect database schema and write pre-compiled `storage/cache/schema_columns.php` |
| `spinx queue:work` | Poll and process the DB-backed job queue |
| `spinx schedule:run` | Run all tasks in `schedule.php` that are due right now (invoke every minute via OS cron) |

## API & OpenAPI

| Command | Description |
|---|---|
| `spinx openapi:generate [--output=path]` | Generate OpenAPI 3.1 schema from routes and controller attributes |

## Serving & Building

| Command | Description |
|---|---|
| `spinx serve` | Boot the active driver (RoadRunner or Swoole) + Vite dev server (HMR) |
| `spinx build` | Production build: compiled frontend assets + primed backend cache |
| `spinx driver:swap <roadrunner\|swoole>` | Switch the active runtime driver |

## Preview & Mobile

| Command | Description |
|---|---|
| `spinx preview --mobile` | Open dev server in interactive browser-based mobile preview container |
| `spinx preview --android` | Open dev server on a running/booted Android device or emulator |
| `spinx preview --ios` | Open dev server on the iOS Simulator (macOS + Xcode only) |
| `spinx preview --desktop` | Open dev server in a native desktop webview window |
| `spinx build:mobile --android` | Scaffold a native Android shell in `mobile/android/` |
| `spinx build:mobile --ios` | Scaffold a native iOS shell in `mobile/ios/` |
