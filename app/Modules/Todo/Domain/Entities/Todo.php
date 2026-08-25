<?php

declare(strict_types=1);

namespace App\Modules\Todo\Domain\Entities;

/**
 * Pure Domain Entity representing a Todo item.
 */
final class Todo
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $title,
        public readonly bool $done,
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null,
    ) {
    }

    public static function create(string $title, bool $done = false): self
    {
        return new self(
            id: null,
            title: trim($title),
            done: $done,
            createdAt: date('Y-m-d H:i:s'),
            updatedAt: date('Y-m-d H:i:s'),
        );
    }

    public function withToggledStatus(): self
    {
        return new self(
            id: $this->id,
            title: $this->title,
            done: !$this->done,
            createdAt: $this->createdAt,
            updatedAt: date('Y-m-d H:i:s'),
        );
    }
}
