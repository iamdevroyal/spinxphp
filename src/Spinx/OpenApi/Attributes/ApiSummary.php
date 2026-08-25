<?php

declare(strict_types=1);

namespace Spinx\OpenApi\Attributes;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
final class ApiSummary
{
    public function __construct(
        public readonly string $summary,
        public readonly string $description = '',
    ) {
    }
}
