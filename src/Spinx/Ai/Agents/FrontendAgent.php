<?php

declare(strict_types=1);

namespace Spinx\Ai\Agents;

use Spinx\Ai\Anthropic\PromptTemplates;

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
        $base    = PromptTemplates::baseSystemPrompt($context);

        return <<<PROMPT
{$base}

## Frontend Agent Focus:
You design modern, responsive, aesthetic view templates in `app/Modules/<Module>/Infrastructure/Views/` using Spinx template directives (`@extends`, `@section`, `@csrf`, `@island`, `@if`, `@foreach`).
PROMPT;
    }
}
