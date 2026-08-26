<?php

declare(strict_types=1);

namespace Spinx\Ai\Tools;

/**
 * Contract for tools callable by Claude via Anthropic Messages Tool Calling schema.
 */
interface ToolInterface
{
    /** Unique tool name */
    public function getName(): string;

    /** Tool description for Claude */
    public function getDescription(): string;

    /** JSON schema defining tool input parameters */
    public function getInputSchema(): array;

    /** Execute the tool with parsed arguments */
    public function execute(array $arguments): array;
}
