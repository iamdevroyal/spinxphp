<?php

declare(strict_types=1);

namespace Spinx\Ai\Agents;

use Spinx\Ai\Anthropic\PromptTemplates;

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
        $base    = PromptTemplates::baseSystemPrompt($context);

        return <<<PROMPT
{$base}

## Database Agent Focus:
You write timestamp-prefixed migrations in `app/Modules/<Module>/Infrastructure/Persistence/Migrations/` and DBAL Active Record models in `app/Modules/<Module>/Infrastructure/Persistence/Models/`.
- Use `Spinx\Database\Schema\Blueprint` (\$table->id(), \$table->string(), \$table->timestamps()).
- Run `spinx migrate` and `spinx schema:compile` via `run_spinx_command` after creating tables.
PROMPT;
    }
}
