<?php

declare(strict_types=1);

namespace Spinx\OpenApi\Attributes;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class ApiResponse
{
    public function __construct(
        public readonly int    $status = 200,
        public readonly string $description = 'Successful response',
        public readonly ?string $schema = null,
    ) {
    }
}
