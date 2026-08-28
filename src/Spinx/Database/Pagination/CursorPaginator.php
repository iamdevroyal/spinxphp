<?php

declare(strict_types=1);

namespace Spinx\Database\Pagination;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

/**
 * CursorPaginator — Result object for cursor-based pagination.
 *
 * Provides O(1) query pagination by navigating datasets using column comparisons
 * (e.g. `WHERE id > :cursor_val LIMIT 16`) rather than offset calculation.
 */
class CursorPaginator implements JsonSerializable, Countable, IteratorAggregate
{
    protected ?string $nextCursor = null;
    protected ?string $prevCursor = null;
    protected bool $hasMore = false;

    /**
     * @param array<int, mixed> $items Sliced items (count <= $perPage)
     * @param int $perPage Number of items requested per page
     * @param string $cursorCol The unique sort column used as the cursor anchor (default: 'id')
     * @param string $direction Sort direction ('asc' or 'desc')
     * @param bool $hasMore Whether more items exist beyond this page
     * @param mixed $firstItemValue The cursor value of the first item for previous navigation
     */
    public function __construct(
        protected array $items,
        protected int $perPage,
        protected string $cursorCol = 'id',
        protected string $direction = 'asc',
        bool $hasMore = false,
        mixed $firstItemValue = null,
    ) {
        $this->hasMore = $hasMore;

        // Compute next cursor from last item
        if ($this->hasMore && !empty($this->items)) {
            $lastItem = end($this->items);
            $lastVal  = is_object($lastItem) ? ($lastItem->{$this->cursorCol} ?? null) : ($lastItem[$this->cursorCol] ?? null);
            if ($lastVal !== null) {
                $this->nextCursor = (new Cursor($lastVal, $this->cursorCol, $this->direction))->encode();
            }
        }

        // Compute prev cursor from first item if requested
        if ($firstItemValue !== null) {
            $this->prevCursor = (new Cursor(
                $firstItemValue,
                $this->cursorCol,
                $this->direction === 'asc' ? 'desc' : 'asc'
            ))->encode();
        }
    }

    public function items(): array
    {
        return $this->items;
    }

    public function perPage(): int
    {
        return $this->perPage;
    }

    public function nextCursor(): ?string
    {
        return $this->nextCursor;
    }

    public function prevCursor(): ?string
    {
        return $this->prevCursor;
    }

    public function hasMore(): bool
    {
        return $this->hasMore;
    }

    public function toArray(): array
    {
        return [
            'data' => $this->items,
            'pagination' => [
                'per_page'    => $this->perPage,
                'next_cursor' => $this->nextCursor,
                'prev_cursor' => $this->prevCursor,
                'has_more'    => $this->hasMore,
            ],
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }
}
