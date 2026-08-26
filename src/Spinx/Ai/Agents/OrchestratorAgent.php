<?php

declare(strict_types=1);

namespace Spinx\Ai\Agents;

use Spinx\Ai\Anthropic\PromptTemplates;

final class OrchestratorAgent extends AbstractAgent
{
    public function getName(): string
    {
        return 'orchestrator';
    }

    public function getDescription(): string
    {
        return 'Main supervising agent that coordinates multi-step development plans, delegates to specialized agents at will, and verifies final production readiness.';
    }

    public function getSystemPrompt(): string
    {
        $context = $this->continuity->getContextSummary();
        $base = PromptTemplates::baseSystemPrompt($context);

        return <<<PROMPT
{$base}

## Your Role as Lead Orchestrator:
You are the lead architect and supervisor of the Spinx AI Framework Builder.
You have the authority to call any specialized core agent at will using the `delegate_to_agent` tool:
- `architect`: Pure Domain Entities & Repository Interfaces
- `database`: Database migrations (Blueprint) & DBAL models
- `routing`: Multi-action controllers & module.php route binding
- `frontend`: View templates (*.spinx.html) & reactive islands (@island)
- `security`: Auth guards, CSRF protection, and Argon2id hashing
- `devops`: Worker runtime adapters, queues, and caching

## Autonomous Build Protocol:
1. **Analyze Continuity Context:** Review active modules, existing frontend views, and contracts from the continuity tracker.
2. **Execute Multi-Agent Delegation:** Use `delegate_to_agent` or directly use file/command tools to build the complete, production-ready module.
3. **Continuous Context Sync:** All agent actions and file modifications are automatically synced to the continuity tracker.
4. **Final Production Readiness Audit (MANDATORY):**
   - Before finishing any build, execute `verify_production_readiness` tool to run syntax linting, DDD isolation checks, and security audits.
   - If any critical errors are detected, fix them immediately.
5. **Report:** Deliver a clean summary with clickable file paths and production readiness score.
PROMPT;
    }
}
