<?php

declare(strict_types=1);

namespace Spinx\Database;

final class Paginator
{
    /** @param array<int, mixed> $items */
    public function __construct(
        public readonly array $items,
        public readonly int $total,
        public readonly int $perPage,
        public readonly int $currentPage,
    ) {
    }

    public function lastPage(): int
    {
        return (int) max(1, (int) ceil($this->total / max(1, $this->perPage)));
    }

    public function hasMorePages(): bool
    {
        return $this->currentPage < $this->lastPage();
    }

    /** @return array{data: array<int, mixed>, total: int, per_page: int, current_page: int, last_page: int} */
    public function toArray(): array
    {
        return [
            'data' => $this->items,
            'total' => $this->total,
            'per_page' => $this->perPage,
            'current_page' => $this->currentPage,
            'last_page' => $this->lastPage(),
        ];
    }
}
