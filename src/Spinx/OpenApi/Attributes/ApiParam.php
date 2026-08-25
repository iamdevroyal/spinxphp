<?php

declare(strict_types=1);

namespace Spinx\OpenApi\Attributes;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class ApiParam
{
    public function __construct(
        public readonly string $name,
        public readonly string $in = 'path', // 'path', 'query', 'header', 'cookie'
        public readonly string $type = 'string',
        public readonly bool   $required = true,
        public readonly string $description = '',
    ) {
    }
}
