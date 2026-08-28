<?php

declare(strict_types=1);

namespace Spinx\Ai\Agents;

use Spinx\Ai\Anthropic\PromptTemplates;

final class RoutingAgent extends AbstractAgent
{
    public function getName(): string
    {
        return 'routing';
    }

    public function getDescription(): string
    {
        return 'Specialized in multi-action controllers, REST/JSON APIs, JsonResource data transformers, routing DSL in module.php, Request::validate(), and API middleware.';
    }

    public function getSystemPrompt(): string
    {
        $context = $this->continuity->getContextSummary();
        $base    = PromptTemplates::baseSystemPrompt($context);

        return <<<PROMPT
{$base}

## Routing & Controller Agent Focus:
You create unified multi-action controllers in `app/Modules/<Module>/Infrastructure/Http/Controllers/` and wire routes in `app/Modules/<Module>/module.php`.

### API & Web Controller Standards:
- Controllers must ONLY handle: HTTP extraction, `Request::validate()`, delegating to Application Services, and returning `view()`, `redirect()`, or `Response::json()`.
- Use `use Spinx\Http\Request;` and `use Spinx\Http\Response;`. Never import raw Symfony Response in controllers.
- For API endpoints: ALWAYS transform entities through `JsonResource` or `ResourceCollection` classes (e.g. `return ProjectResource::make($project)->response();`). Never return raw database models directly.
- For high-scale feeds/lists: Use `Model::cursorPaginate(20, 'id', $cursor)` and pass to `ProjectResource::collection($feed)`.
- For API-only / Headless backend mode (`--frontend=none`): Never use `Response::view()`; all controllers return clean JSON resources.
- For protected API routes: Group routes under `['middleware' => ['auth:api']]` and attach `->middleware('ability:scope')` when granular abilities are required.
- Attach `->middleware('cache.headers:max_age=3600,etag')` to high-volume read endpoints for sub-0.1ms 304 caching.
- Attach `->middleware('idempotent')` to mutation endpoints (payments, AI generation, orders) to prevent duplicate runs.
- For Webhooks: Always chain `->withoutCsrf()` on webhook endpoints (e.g. Stripe, GitHub, Slack) and verify HMAC signatures using `HmacWebhookVerifier`.
PROMPT;
    }
}

