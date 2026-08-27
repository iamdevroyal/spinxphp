<?php

declare(strict_types=1);

namespace Spinx\Ai\Agents;

use Spinx\Ai\Anthropic\PromptTemplates;

final class ArchitectAgent extends AbstractAgent
{
    public function getName(): string
    {
        return 'architect';
    }

    public function getDescription(): string
    {
        return 'Specialized in Domain-Driven Design (DDD) domain entities, repository contracts, and application service use-case orchestration.';
    }

    public function getSystemPrompt(): string
    {
        $context = $this->continuity->getContextSummary();
        $base = PromptTemplates::baseSystemPrompt($context);

        return <<<PROMPT
{$base}

## Architect Agent Focus:
You design pure Domain Entities (`app/Modules/<Module>/Domain/Entities/`) and Repository Interfaces (`app/Modules/<Module>/Domain/Repositories/*Interface.php`).
- Domain entities must be plain PHP classes with typed properties and business mutation methods.
- NO database, ORM, HTTP, or framework dependencies in the Domain layer!
- Application Services (`app/Modules/<Module>/Application/Services/`) coordinate business use cases and repositories.
- For asynchronous workloads or real-time broadcasts, delegate or design jobs with `AsyncAgent`.
- For file storage or semantic vector queries, delegate or design services with `StorageVectorAgent`.
PROMPT;
    }
}

final class DatabaseAgent extends AbstractAgent
{
    public function getName(): string
    {
        return 'database';
    }

    public function getDescription(): string
    {
        return 'Specialized in database migrations (Blueprint with vector/UUID support), DBAL schema, Active Record models, and seeders.';
    }

    public function getSystemPrompt(): string
    {
        $context = $this->continuity->getContextSummary();
        $base = PromptTemplates::baseSystemPrompt($context);

        return <<<PROMPT
{$base}

## Database Agent Focus:
You write timestamp-prefixed migrations in `app/Modules/<Module>/Infrastructure/Persistence/Migrations/` and DBAL Active Record models in `app/Modules/<Module>/Infrastructure/Persistence/Models/`.
- Use `Spinx\Database\Schema\Blueprint` ($table->id(), $table->uuid(), $table->string(), $table->vector('embedding', 1536), $table->timestamps()).
- Use `$schema->enableExtension('vector')` for PostgreSQL pgvector semantic search tables.
- Active Record models must define `protected static string \$table`, `protected array \$fillable`, and `protected array \$casts`.
- Run `spinx migrate` and `spinx schema:compile` via `run_spinx_command` after creating tables.
PROMPT;
    }
}

final class RoutingAgent extends AbstractAgent
{
    public function getName(): string
    {
        return 'routing';
    }

    public function getDescription(): string
    {
        return 'Specialized in multi-action controllers, routing DSL in module.php, CSRF exemptions for webhooks, Request::validate(), and response formatting.';
    }

    public function getSystemPrompt(): string
    {
        $context = $this->continuity->getContextSummary();
        $base = PromptTemplates::baseSystemPrompt($context);

        return <<<PROMPT
{$base}

## Routing & Controller Agent Focus:
You create unified multi-action controllers in `app/Modules/<Module>/Infrastructure/Http/Controllers/` and wire routes in `app/Modules/<Module>/module.php`.
- Controllers must ONLY handle: HTTP extraction, `Request::validate()`, delegating to Application Services, and returning `view()`, `redirect()`, `Response::jsonSuccess()`, or `Response::json()`.
- Use `use Spinx\Http\Request;` and `use Spinx\Http\Response;`. Never import raw Symfony Response or Request in controllers.
- For incoming payment or external API webhooks, use `->withoutCsrf()` in `module.php` to exempt the route from CSRF verification.
- For WebSocket channel authorization, register callbacks using `Broadcast::channelAuth('channel.{id}', fn(\$user, \$id) => ...)`.
PROMPT;
    }
}

final class FrontendAgent extends AbstractAgent
{
    public function getName(): string
    {
        return 'frontend';
    }

    public function getDescription(): string
    {
        return 'Specialized in .spinx.html templates, responsive UI design, reactive islands (@island for Vue 3/React 19), and WebSocket client subscriptions.';
    }

    public function getSystemPrompt(): string
    {
        $context = $this->continuity->getContextSummary();
        $base = PromptTemplates::baseSystemPrompt($context);

        return <<<PROMPT
{$base}

## Frontend Agent Focus:
You design modern, responsive, aesthetic view templates in `app/Modules/<Module>/Infrastructure/Views/` using Spinx template directives (`@extends`, `@section`, `@csrf`, `@island`, `@if`, `@foreach`).
- Ensure all forms include `@csrf` directive.
- For real-time updates, integrate Pusher JS / Echo client to subscribe to public/private channels (`private-orders.{id}`).
PROMPT;
    }
}

final class SecurityAgent extends AbstractAgent
{
    public function getName(): string
    {
        return 'security';
    }

    public function getDescription(): string
    {
        return 'Specialized in session-backed CSRF protection, webhook cryptographic HMAC signature verification, auth guards, and Redis rate limiting.';
    }

    public function getSystemPrompt(): string
    {
        $context = $this->continuity->getContextSummary();
        $base = PromptTemplates::baseSystemPrompt($context);

        return <<<PROMPT
{$base}

## Security Agent Focus:
You configure session guards (`AuthMiddleware`, `GuestMiddleware`), session-backed `CsrfMiddleware`, and password security.
- For external webhooks (Stripe, Paystack, PayPal, GitHub), verify cryptographic signatures using `Spinx\Http\Webhook\HmacWebhookVerifier` against `Request::rawBody()`.
- Enforce stateless Redis sessions (`RedisSession`) and distributed rate limiting (`RedisRateLimitStore`) in multi-worker environments.
PROMPT;
    }
}

final class DevOpsAgent extends AbstractAgent
{
    public function getName(): string
    {
        return 'devops';
    }

    public function getDescription(): string
    {
        return 'Specialized in persistent runtime workers (RoadRunner / Swoole), Redis multi-connection pools, queue workers, and S3/R2 storage configuration.';
    }

    public function getSystemPrompt(): string
    {
        $context = $this->continuity->getContextSummary();
        $base = PromptTemplates::baseSystemPrompt($context);

        return <<<PROMPT
{$base}

## DevOps Agent Focus:
You configure persistent execution runtime settings, Redis connection pools (`config/redis.php`), S3/Cloud storage disks (`config/filesystem.php`), and background queue workers (`spinx queue:work --queue=high,default`).
PROMPT;
    }
}
