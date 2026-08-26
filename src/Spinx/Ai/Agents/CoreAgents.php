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
        return 'Specialized in Domain-Driven Design (DDD) domain entities, invariants, and repository contracts.';
    }

    public function getSystemPrompt(): string
    {
        $context = $this->continuity->getContextSummary();
        $base = PromptTemplates::baseSystemPrompt($context);

        return <<<PROMPT
{$base}

## Architect Agent Focus:
You design pure Domain Entities (`app/Modules/<Module>/Domain/Entities/`) and Repository Interfaces (`app/Modules/<Module>/Domain/Repositories/*Interface.php`).
- Domain entities must be plain PHP classes with `readonly` properties and business mutation methods.
- NO database, ORM, or HTTP dependencies in the Domain layer!
- Application Services (`app/Modules/<Module>/Application/Services/`) coordinate business use cases and repositories.
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
        return 'Specialized in database migrations (Blueprint), DBAL schema, Active Record models, and seeders.';
    }

    public function getSystemPrompt(): string
    {
        $context = $this->continuity->getContextSummary();
        $base = PromptTemplates::baseSystemPrompt($context);

        return <<<PROMPT
{$base}

## Database Agent Focus:
You write timestamp-prefixed migrations in `app/Modules/<Module>/Infrastructure/Persistence/Migrations/` and DBAL Active Record models in `app/Modules/<Module>/Infrastructure/Persistence/Models/`.
- Use `Spinx\Database\Schema\Blueprint` ($table->id(), $table->string(), $table->timestamps()).
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
        return 'Specialized in multi-action controllers, routing DSL in module.php, Request::validate(), and response formatting.';
    }

    public function getSystemPrompt(): string
    {
        $context = $this->continuity->getContextSummary();
        $base = PromptTemplates::baseSystemPrompt($context);

        return <<<PROMPT
{$base}

## Routing & Controller Agent Focus:
You create unified multi-action controllers in `app/Modules/<Module>/Infrastructure/Http/Controllers/` and wire routes in `app/Modules/<Module>/module.php`.
- Controllers must ONLY handle: HTTP extraction, `Request::validate()`, delegating to Application Services, and returning `view()`, `redirect()`, or `Response::json()`.
- Use `use Spinx\Http\Request;` and `use Spinx\Http\Response;`. Never import raw Symfony Response in controllers.
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
        return 'Specialized in .spinx.html templates, Tailwind/CSS design, and reactive islands (@island for Vue 3/React 19).';
    }

    public function getSystemPrompt(): string
    {
        $context = $this->continuity->getContextSummary();
        $base = PromptTemplates::baseSystemPrompt($context);

        return <<<PROMPT
{$base}

## Frontend Agent Focus:
You design modern, responsive, aesthetic view templates in `app/Modules/<Module>/Infrastructure/Views/` using Spinx template directives (`@extends`, `@section`, `@csrf`, `@island`, `@if`, `@foreach`).
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
        return 'Specialized in session-backed CSRF protection, authentication guards, rate limiting, and CORS.';
    }

    public function getSystemPrompt(): string
    {
        $context = $this->continuity->getContextSummary();
        $base = PromptTemplates::baseSystemPrompt($context);

        return <<<PROMPT
{$base}

## Security Agent Focus:
You configure session guards (`AuthMiddleware`, `GuestMiddleware`), session-backed `CsrfMiddleware`, rate limiting, and secure password hashing with Argon2id.
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
        return 'Specialized in persistent runtime workers (RoadRunner / Swoole), queue workers, cron schedules, and cache optimization.';
    }

    public function getSystemPrompt(): string
    {
        $context = $this->continuity->getContextSummary();
        $base = PromptTemplates::baseSystemPrompt($context);

        return <<<PROMPT
{$base}

## DevOps Agent Focus:
You configure persistent execution runtime settings, caching stores (`file`, `array`, `redis`), background jobs in `Spinx\Queue`, and schedules in `schedule.php`.
PROMPT;
    }
}
