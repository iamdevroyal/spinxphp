<?php

declare(strict_types=1);

namespace Spinx\Http\Resources;

use JsonSerializable;
use Spinx\Http\Response;

/**
 * JsonResource — Standard transformation layer for Models and Domain Entities.
 *
 * Sits between Eloquent/ActiveRecord Models or Domain Entities and the HTTP JSON Response,
 * guaranteeing consistent JSON contracts, preventing accidental column leaks, and
 * supporting conditional relationship/attribute loading.
 *
 * Usage:
 *
 *   final class UserResource extends JsonResource
 *   {
 *       public function toArray(): array
 *       {
 *           return [
 *               'id'         => $this->id,
 *               'name'       => $this->name,
 *               'email'      => $this->email,
 *               'secret_key' => $this->when($this->isAdmin(), $this->secret_key),
 *               'posts'      => UserPostResource::collection($this->whenLoaded('posts')),
 *               'created_at' => $this->created_at?->format('c'),
 *           ];
 *       }
 *   }
 *
 *   // In Controller:
 *   return UserResource::make($user);
 *   return UserResource::collection($users);
 */
class JsonResource implements JsonSerializable
{
    /** The top-level data wrapper key. Set to null for unwrapped responses. */
    public static ?string $wrap = 'data';

    /** Additional metadata to merge into the top-level response envelope. */
    public array $with = [];

    /** Custom HTTP status code when converting directly to a Response. */
    protected int $status = 200;

    /** Custom headers when converting directly to a Response. */
    protected array $headers = [];

    public function __construct(
        public mixed $resource,
    ) {
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Factories
    // ─────────────────────────────────────────────────────────────────────────

    public static function make(mixed $resource): static
    {
        return new static($resource);
    }

    public static function collection(mixed $resource): ResourceCollection
    {
        return new ResourceCollection($resource, static::class);
    }

    public static function withoutWrapping(): void
    {
        static::$wrap = null;
    }

    public static function wrap(string $key): void
    {
        static::$wrap = $key;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Transformation Contract
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Transform the underlying resource into an array representation.
     * Override this method in your concrete resource classes.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        if ($this->resource === null) {
            return [];
        }

        if (is_array($this->resource)) {
            return $this->resource;
        }

        if (method_exists($this->resource, 'toArray')) {
            return $this->resource->toArray();
        }

        if ($this->resource instanceof JsonSerializable) {
            return (array) $this->resource->jsonSerialize();
        }

        return (array) $this->resource;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Conditional Attributes
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Include an attribute only when the given condition is truthy.
     */
    public function when(bool $condition, mixed $value, mixed $default = null): mixed
    {
        if ($condition) {
            return is_callable($value) ? $value() : $value;
        }

        return $default !== null ? (is_callable($default) ? $default() : $default) : new MissingValue();
    }

    /**
     * Include an attribute or relation only if it has already been loaded on the model/entity.
     */
    public function whenLoaded(string $relationship, mixed $value = null, mixed $default = null): mixed
    {
        $isLoaded = false;

        if (is_object($this->resource)) {
            if (method_exists($this->resource, 'relationLoaded')) {
                $isLoaded = $this->resource->relationLoaded($relationship);
            } elseif (isset($this->resource->{$relationship})) {
                $isLoaded = true;
            }
        } elseif (is_array($this->resource) && array_key_exists($relationship, $this->resource)) {
            $isLoaded = true;
        }

        if ($isLoaded) {
            if ($value !== null) {
                return is_callable($value) ? $value() : $value;
            }
            return is_object($this->resource) ? $this->resource->{$relationship} : $this->resource[$relationship];
        }

        return $default !== null ? (is_callable($default) ? $default() : $default) : new MissingValue();
    }

    /**
     * Include an attribute only when it is not null.
     */
    public function whenNotNull(mixed $value): mixed
    {
        if ($value === null) {
            return new MissingValue();
        }

        return is_callable($value) ? $value() : $value;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Envelope & Serialization
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Resolve the resource to an array, filtering out any MissingValue sentinels.
     *
     * @return array<string, mixed>
     */
    public function resolve(): array
    {
        $data = $this->toArray();

        return $this->filterMissing($data);
    }

    /**
     * JsonSerializable implementation.
     */
    public function jsonSerialize(): mixed
    {
        $resolved = $this->resolve();

        if (static::$wrap !== null) {
            return array_merge([static::$wrap => $resolved], $this->with);
        }

        return !empty($this->with) ? array_merge($resolved, $this->with) : $resolved;
    }

    /**
     * Convert this resource into a Spinx HTTP Response.
     */
    public function response(int $status = 200, array $headers = []): Response
    {
        return Response::json($this->jsonSerialize(), $status, $headers);
    }

    /**
     * Recursively strip MissingValue instances from transformed arrays.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function filterMissing(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            if ($value instanceof MissingValue) {
                continue;
            }

            if (is_array($value)) {
                $result[$key] = $this->filterMissing($value);
            } elseif ($value instanceof self) {
                $result[$key] = $value->resolve();
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Resource Delegation
    // ─────────────────────────────────────────────────────────────────────────

    public function __get(string $key): mixed
    {
        if (is_object($this->resource)) {
            return $this->resource->{$key} ?? null;
        }

        if (is_array($this->resource)) {
            return $this->resource[$key] ?? null;
        }

        return null;
    }

    public function __call(string $method, array $parameters): mixed
    {
        if (is_object($this->resource) && method_exists($this->resource, $method)) {
            return $this->resource->{$method}(...$parameters);
        }

        throw new \BadMethodCallException("Method [{$method}] does not exist on resource or underlying entity.");
    }

    public function __isset(string $key): bool
    {
        if (is_object($this->resource)) {
            return isset($this->resource->{$key});
        }

        if (is_array($this->resource)) {
            return isset($this->resource[$key]);
        }

        return false;
    }
}
