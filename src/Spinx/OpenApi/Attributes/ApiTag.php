<?php

declare(strict_types=1);

namespace Spinx\OpenApi\Attributes;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class ApiTag
{
    public function __construct(
        public readonly string $tag,
    ) {
    }
}
