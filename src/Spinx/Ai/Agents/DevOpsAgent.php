<?php

declare(strict_types=1);

namespace Spinx\Ai\Agents;

use Spinx\Ai\Anthropic\PromptTemplates;

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
        $base    = PromptTemplates::baseSystemPrompt($context);

        return <<<PROMPT
{$base}

## DevOps Agent Focus:
You configure persistent execution runtime settings, caching stores (`file`, `array`, `redis`), background jobs in `Spinx\Queue`, and schedules in `schedule.php`.
PROMPT;
    }
}
