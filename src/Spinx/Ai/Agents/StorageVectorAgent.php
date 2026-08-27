<?php

declare(strict_types=1);

namespace Spinx\Ai\Agents;

use Spinx\Ai\Anthropic\ClaudeClient;
use Spinx\Ai\Anthropic\PromptTemplates;
use Spinx\Ai\Continuity\ContinuityTracker;
use Spinx\Ai\Tools\ToolRegistry;

/**
 * Specialized Spinx AI Builder agent for Filesystem/Object Storage, Vector Embeddings, and Semantic Search.
 */
final class StorageVectorAgent extends AbstractAgent
{
    public function getName(): string
    {
        return 'storage_vector';
    }

    public function getDescription(): string
    {
        return 'Specialist in multi-disk object storage (S3/R2/Local), temporary signed URLs, semantic vector embeddings, and AI LLM integrations.';
    }

    public function getSystemPrompt(): string
    {
        $context = $this->continuity->getContextSummary();
        $base = PromptTemplates::baseSystemPrompt($context);

        return <<<PROMPT
{$base}

## StorageVectorAgent Specialization:
You are the **Storage & Vector AI Specialist**.
Your responsibilities:
1. **Multi-Disk Filesystem & Cloud Storage:**
   - Use `Spinx\Filesystem\Storage` facade.
   - Support `local`, `s3`, `r2`, `minio` disks seamlessly: `Storage::disk('s3')->put(\$path, \$contents)`.
   - Generate secure temporary signed download URLs: `Storage::disk('s3')->temporaryUrl(\$path, now()->addHours(2))`.
   - Handle directory traversal and streaming safely without leaking file descriptors.

2. **Semantic Vector Search & DBAL Embeddings:**
   - Use `Spinx\Database\Vector\Vector` facade.
   - Generate embeddings: `Vector::embed(\$text)`.
   - Execute similarity queries: `Vector::search('table', 'embedding', \$queryVector, ['status' => 'active'], 10, 'cosine')`.
   - Work with `DatabaseAgent` to ensure `\$table->vector('embedding', 1536)` and `\$schema->enableExtension('vector')` are present in migrations.

3. **Application AI & LLM Bridge:**
   - Use `Spinx\Llm\Llm` facade for project-level AI generation: `Llm::chat('prompt')` or `Llm::provider('openai')->generate(\$request)`.

Output clean, production-ready PHP 8.2+ code adhering strictly to Spinx standards.
PROMPT;
    }
}
