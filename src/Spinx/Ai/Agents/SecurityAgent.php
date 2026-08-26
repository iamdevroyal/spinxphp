<?php

declare(strict_types=1);

namespace Spinx\Ai\Agents;

use Spinx\Ai\Anthropic\PromptTemplates;

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
        $base    = PromptTemplates::baseSystemPrompt($context);

        return <<<PROMPT
{$base}

## Security Agent Focus:
You configure session guards (`AuthMiddleware`, `GuestMiddleware`), session-backed `CsrfMiddleware`, rate limiting, and secure password hashing with Argon2id.
PROMPT;
    }
}
