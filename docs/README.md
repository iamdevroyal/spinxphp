# Spinx Framework Documentation Index

Welcome to the official documentation for the **Spinx PHP Framework (v1.0.17+)**.

Spinx is engineered for high-throughput persistent execution runtimes (RoadRunner & Swoole), Kernel-Enforced Domain-Driven Design (DDD), and modern full-stack web applications.

---

## 📚 Documentation Directory

### 1. Core Architecture & Runtime
- [**Architecture & Overview**](architecture.md) — Persistent execution model, worker lifecycle, and zero-leak memory isolation.
- [**Getting Started**](getting-started.md) — Installation, prerequisites, project initialization, and dev server boot.
- [**Runtime Drivers (RoadRunner & Swoole)**](runtime-drivers.md) — Configuring Go supervisor and coroutine execution adapters.
- [**Configuration (`spinx.json` & `.env`)**](configuration.md) — Centralized framework settings and environment loading.

### 2. Domain-Driven Design & Application Development
- [**Routing & Controllers**](routing-and-controllers.md) — Multi-action controllers, fluent route DSL in `module.php`, `->withoutCsrf()`, and request/response facades.
- [**Validation Engine**](validation.md) — Declarative rules, sanitization, allowlists, and custom validators.
- [**Database, Active Record & Migrations**](database.md) — DBAL 4 ORM, ahead-of-time schema caching, timestamped migrations, and transactions.
- [**Authentication & Sessions**](auth.md) — Password hashing with Argon2id, session guards, and `RedisSession` multi-worker scaling.
- [**Templating & Reactive Islands**](templating.md) — `*.spinx.html` template directives (`@extends`, `@section`, `@csrf`, `@island`) for Vue 3 and React 19.

### 3. Modern Subsystems
- [**Asynchronous Queues & Worker Daemons**](queues.md) — Priority queues, delayed dispatch, retry backoffs, worker daemons, and HMAC anti-tampering.
- [**Real-Time Event Broadcasting (WebSockets)**](broadcasting.md) — Native Pusher protocol (Soketi/Pusher/Reverb), public/private/presence channels, and auth routes.
- [**Multi-Disk Filesystem & Cloud Storage**](storage.md) — Local and S3-compatible cloud storage (AWS S3, Cloudflare R2, MinIO) with signed temporary URLs.
- [**Semantic Vector Search & DBAL Extensions**](vector-search.md) — OpenAI/Ollama vector embeddings, cosine distance querying, and PostgreSQL `pgvector`.
- [**Application LLM Bridge**](llm-bridge.md) — Generic AI layer supporting Anthropic & OpenAI with structured JSON parsing.
- [**Redis Connection Pooling & Distributed State**](redis-and-state.md) — Database index separation, session storage, and atomic rate limiting.
- [**Production Security & Hardening**](security.md) — Cryptographic webhook signatures, CSRF coroutine isolation, CORS matching, and production AI route shielding.

### 4. AI Tooling & Ecosystem
- [**Autonomous Spinx AI Builder**](ai-builder.md) — 9-Agent autonomous engineering fleet, architectural guardrails, anti-pattern detection, and production readiness audits.
- [**CLI Reference**](cli-reference.md) — Complete command reference (`spinx make:*`, `spinx migrate`, `spinx queue:work`, `spinx ai:*`).
- [**OpenAPI 3.1 Specification Generator**](openapi.md) — Auto-generating API schemas via route reflection.
- [**Mobile & Desktop Shells**](mobile-and-desktop.md) — Browser mobile preview container and native Android / iOS / Desktop shell generators.
