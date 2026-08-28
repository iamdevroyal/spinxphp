<?php

declare(strict_types=1);

namespace Spinx\Http\Resources;

use Countable;
use IteratorAggregate;
use ArrayIterator;
use Traversable;

/**
 * ResourceCollection — Manages transformed collections of JsonResource instances,
 * automatically handling array mapping and pagination metadata envelopes.
 */
class ResourceCollection extends JsonResource implements Countable, IteratorAggregate
{
    /** @var string|null The resource class used to collect each individual item. */
    public ?string $collects = null;

    /** @var array<string, mixed> Additional envelope data. */
    public array $additional = [];

    public function __construct(
        mixed $resource,
        ?string $collects = null,
    ) {
        parent::__construct($resource);
        $this->collects = $collects;
    }

    /**
     * Transform the resource collection into an array.
     *
     * @return array<int, mixed>
     */
    public function toArray(): array
    {
        $items = $this->extractItems();
        $collectsClass = $this->collects ?? JsonResource::class;

        $results = [];
        foreach ($items as $item) {
            if ($item instanceof JsonResource) {
                $results[] = $item->resolve();
            } elseif (class_exists($collectsClass)) {
                $resourceInstance = new $collectsClass($item);
                $results[] = $resourceInstance->resolve();
            } else {
                $results[] = $item;
            }
        }

        return $results;
    }

    /**
     * JsonSerializable implementation for collections.
     */
    public function jsonSerialize(): mixed
    {
        $data = $this->toArray();
        $envelope = [];

        if (static::$wrap !== null) {
            $envelope[static::$wrap] = $data;
        } else {
            $envelope = $data;
        }

        // Attach pagination metadata if underlying resource is paginated
        $paginationMeta = $this->extractPaginationMeta();
        if (!empty($paginationMeta)) {
            $envelope = is_array($envelope) ? array_merge($envelope, $paginationMeta) : $envelope;
        }

        if (!empty($this->additional)) {
            $envelope = is_array($envelope) ? array_merge($envelope, $this->additional) : $envelope;
        }

        return $envelope;
    }

    /**
     * Attach extra metadata to the response envelope.
     *
     * @param array<string, mixed> $data
     */
    public function additional(array $data): static
    {
        $this->additional = array_merge($this->additional, $data);
        return $this;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @return iterable<mixed>
     */
    protected function extractItems(): iterable
    {
        if ($this->resource === null) {
            return [];
        }

        if (is_array($this->resource)) {
            return $this->resource;
        }

        if (is_object($this->resource)) {
            if (method_exists($this->resource, 'items')) {
                return $this->resource->items();
            }
            if (property_exists($this->resource, 'items') && is_iterable($this->resource->items)) {
                return $this->resource->items;
            }
            if ($this->resource instanceof Traversable) {
                return $this->resource;
            }
        }

        return (array) $this->resource;
    }

    /**
     * Extract pagination metadata if the resource is paginated.
     *
     * @return array<string, mixed>
     */
    protected function extractPaginationMeta(): array
    {
        if (!is_object($this->resource)) {
            return [];
        }

        // Standard Offset Paginator
        if (method_exists($this->resource, 'currentPage') && method_exists($this->resource, 'total')) {
            return [
                'pagination' => [
                    'current_page' => $this->resource->currentPage(),
                    'per_page'     => $this->resource->perPage(),
                    'total'        => $this->resource->total(),
                    'last_page'    => method_exists($this->resource, 'lastPage') ? $this->resource->lastPage() : null,
                    'has_more'     => method_exists($this->resource, 'hasMorePages') ? $this->resource->hasMorePages() : null,
                ],
            ];
        }

        // Cursor Paginator
        if (method_exists($this->resource, 'nextCursor')) {
            return [
                'pagination' => [
                    'per_page'    => method_exists($this->resource, 'perPage') ? $this->resource->perPage() : 15,
                    'next_cursor' => $this->resource->nextCursor(),
                    'prev_cursor' => method_exists($this->resource, 'prevCursor') ? $this->resource->prevCursor() : null,
                    'has_more'    => method_exists($this->resource, 'hasMore') ? $this->resource->hasMore() : false,
                ],
            ];
        }

        return [];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Countable & IteratorAggregate
    // ─────────────────────────────────────────────────────────────────────────

    public function count(): int
    {
        $items = $this->extractItems();
        return is_countable($items) ? count($items) : iterator_count(new ArrayIterator((array) $items));
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->toArray());
    }
}
