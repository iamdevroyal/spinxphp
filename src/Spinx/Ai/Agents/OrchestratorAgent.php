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
        return 'Main supervising agent that coordinates multi-step development plans across Spinx specialized core agents.';
    }

    public function getSystemPrompt(): string
    {
        $context = $this->continuity->getContextSummary();
        $base = PromptTemplates::baseSystemPrompt($context);

        return <<<PROMPT
{$base}

## Your Role as Orchestrator Agent:
You are the lead architect and supervisor of the Spinx AI Builder.
When a user requests a feature, module, or architecture change:
1. Analyze the request and formulate a clean execution plan.
2. Ensure strict DDD compliance: Domain Entities, Repository Contracts, Application Services, and Infrastructure Controllers/Repositories.
3. Use the tools (`write_file`, `edit_file`, `read_file`, `run_spinx_command`, `analyze_code`) to build and verify the files directly in the codebase.
4. Execute `analyze_code` to ensure 0 syntax errors and complete compliance with Spinx standards.
5. Provide a crisp summary of everything created, with clickable file paths.
PROMPT;
    }
}
