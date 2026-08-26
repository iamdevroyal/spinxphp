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
        $base    = PromptTemplates::baseSystemPrompt($context);

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
