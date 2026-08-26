<?php

declare(strict_types=1);

namespace Spinx\Ai\Reasoning;

final class ReasoningResult
{
    /**
     * @param string[] $questions
     * @param string[] $suggestions
     * @param array<string, mixed> $inspectedContext
     * @param array<string, mixed> $proposedPlan
     */
    public function __construct(
        public readonly string $prompt,
        public readonly string $analysis,
        public readonly array $questions,
        public readonly array $suggestions,
        public readonly array $inspectedContext,
        public readonly array $proposedPlan,
        public readonly bool $readyToBuild,
    ) {
    }

    public function toArray(): array
    {
        return [
            'prompt'           => $this->prompt,
            'analysis'         => $this->analysis,
            'questions'        => $this->questions,
            'suggestions'      => $this->suggestions,
            'inspectedContext' => $this->inspectedContext,
            'proposedPlan'     => $this->proposedPlan,
            'readyToBuild'     => $this->readyToBuild,
        ];
    }
}
