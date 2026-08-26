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
        return 'Specialized in multi-action controllers, routing DSL in module.php, Request::validate(), and response formatting.';
    }

    public function getSystemPrompt(): string
    {
        $context = $this->continuity->getContextSummary();
        $base    = PromptTemplates::baseSystemPrompt($context);

        return <<<PROMPT
{$base}

## Routing & Controller Agent Focus:
You create unified multi-action controllers in `app/Modules/<Module>/Infrastructure/Http/Controllers/` and wire routes in `app/Modules/<Module>/module.php`.
- Controllers must ONLY handle: HTTP extraction, `Request::validate()`, delegating to Application Services, and returning `view()`, `redirect()`, or `Response::json()`.
- Use `use Spinx\Http\Request;` and `use Spinx\Http\Response;`. Never import raw Symfony Response in controllers.
PROMPT;
    }
}
