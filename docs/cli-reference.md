# CLI Reference

The Spinx CLI is a local PHP script (`php spinx`) at the project root. All commands must be run from the project root directory.

---

## 📋 Quick Reference

```bash
php spinx <command> [arguments] [options]
```

---

## 🏗️ Project & Application

| Command | Description |
|---|---|
| `php spinx new <name> [--frontend=vue\|react]` | Scaffold a new Spinx application |
| `php spinx serve` | Start persistent worker runtime (RoadRunner/Swoole) + Vite HMR |
| `php spinx driver:swap <roadrunner\|swoole>` | Switch runtime driver and update `spinx.json` |
| `php spinx optimize` | Compile schema cache + container bindings for production |
| `php spinx optimize:clear` | Clear all compiled caches (schema, container, views) |

---

## 🧱 Code Generation (`make:*`)

| Command | Description |
|---|---|
| `php spinx make:module <Name>` | Scaffold complete DDD module directory structure |
| `php spinx make:module <Name> --all` | Scaffold module + entity, model, repository, service, controller, migration |
| `php spinx make:module <Name> --all --except=service,model` | Scaffold module, skipping specified generators |
| `php spinx make:entity <Module> <EntityName>` | Create pure Domain entity |
| `php spinx make:model <Module> <ModelName>` | Create DBAL Active Record model |
| `php spinx make:repository <Module> <RepoName>` | Create Repository interface + concrete implementation pair |
| `php spinx make:service <Module> <ServiceName>` | Create Application Service |
| `php spinx make:controller <Module> <ControllerName>` | Create HTTP Controller |
| `php spinx make:middleware <Module> <MiddlewareName>` | Create request Middleware |
| `php spinx make:migration <Module> <snake_case_description>` | Create timestamped database migration |
| `php spinx make:mail <Module> <MailableName>` | Create Mailable class |

---

## 🗄️ Database & Schema

| Command | Description |
|---|---|
| `php spinx migrate` | Run all pending migrations across all modules |
| `php spinx module:migrate <Name>` | Run migrations for a specific module only |
| `php spinx schema:compile` | Compile table column metadata into ahead-of-time cache |
| `php spinx schema:clear` | Clear compiled schema cache |

---

## ⏳ Queues & Background Workers

| Command | Description |
|---|---|
| `php spinx queue:work` | Start queue worker daemon (polls `spinx_jobs` every 1s) |
| `php spinx queue:work --queue=high,default` | Poll multiple queues in priority order |
| `php spinx schedule:run` | Execute all due scheduled tasks (run via system cron: `* * * * *`) |

---

## 🤖 Spinx AI Builder

| Command | Description |
|---|---|
| `php spinx ai:chat` | Start interactive AI engineering chat session |
| `php spinx ai:build "<prompt>"` | Autonomous multi-agent feature build from natural language |
| `php spinx ai:dashboard` | Open AI Builder browser UI (dev/staging only) |

---

## 🔧 Cache & Maintenance

| Command | Description |
|---|---|
| `php spinx cache:clear` | Clear the entire application cache |
| `php spinx cache:forget <key>` | Remove a single cache key |
| `php spinx view:clear` | Clear compiled view templates |
| `php spinx container:clear` | Clear compiled DI container bindings |
| `php spinx log:clear` | Delete all log files in `storage/logs/` |
| `php spinx logs` / `php spinx log:tail` | Tail live application logs |

---

## 📱 Preview & Native Builds

| Command | Description |
|---|---|
| `php spinx preview --mobile` | Launch browser mobile viewport preview |
| `php spinx preview --desktop` | Launch browser desktop viewport preview |
| `php spinx build:mobile android` | Generate native Android (Kotlin WebView) shell |
| `php spinx build:mobile ios` | Generate native iOS (Swift WKWebView) shell |

---

## 📄 API & Documentation

| Command | Description |
|---|---|
| `php spinx openapi:generate` | Auto-generate `public/openapi.json` from route attributes |

---

## 🆘 Help

```bash
php spinx help
php spinx --help
```
