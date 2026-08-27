<?php

declare(strict_types=1);

namespace Spinx\Ai\Context;

/**
 * Loads, caches, and formats the authoritative Spinx Architecture Context
 * for injection into all Spinx AI Builder agent system prompts.
 */
final class FrameworkArchitectureContext
{
    private static ?string $cachedContent = null;

    public function __construct(
        private readonly ?string $projectRoot = null,
    ) {
    }

    /**
     * Get the full authoritative architecture context markdown text.
     */
    public function getFullContext(): string
    {
        if (self::$cachedContent !== null) {
            return self::$cachedContent;
        }

        $possiblePaths = [
            ($this->projectRoot ?? '') . '/resources/ai/SPINX_AI_ARCHITECTURE.md',
            dirname(__DIR__, 4) . '/resources/ai/SPINX_AI_ARCHITECTURE.md',
            __DIR__ . '/../../../../resources/ai/SPINX_AI_ARCHITECTURE.md',
        ];

        foreach ($possiblePaths as $path) {
            if ($path !== '' && is_file($path)) {
                $content = (string) file_get_contents($path);
                if ($content !== '') {
                    return self::$cachedContent = $content;
                }
            }
        }

        return self::$cachedContent = $this->defaultFallbackContext();
    }

    /**
     * Formatted slice for base agent system prompts.
     */
    public function forSystemPrompt(): string
    {
        return $this->getFullContext();
    }

    private function defaultFallbackContext(): string
    {
        return <<<MD
# Spinx Framework Architecture Core Rules
1. Strict Domain-Driven Design (DDD) in `app/Modules/<ModuleName>/` (Domain/, Application/, Infrastructure/, module.php).
2. Pure Domain entities with zero DBAL/HTTP/Framework imports.
3. Native Spinx facades: Request::, Response::, DB::, Model::, Queue::, Broadcast::, Storage::, Vector::, Llm::, Cache::, Log::, Redis::, Auth::.
4. Persistent-Worker Safety: Zero superglobals (\$_SESSION, \$_GET, \$_POST) in code.
5. All routes in `app/Modules/<Name>/module.php`. No global routes/web.php or app/Models.
MD;
    }
}
