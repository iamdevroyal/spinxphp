<?php

declare(strict_types=1);

namespace Spinx\Database;

use Doctrine\DBAL\Connection;
use Spinx\Database\Connection\ConnectionManager;
use Spinx\Database\Relations\{BelongsTo, BelongsToMany, HasMany, HasOne, MorphMany, MorphTo, Relation};

/**
 * Active-record base class providing Eloquent-style feature parity (build
 * spec §7.2) on top of the coroutine-safe QueryBuilder (§7.1) rather than
 * Doctrine's UnitOfWork.
 *
 * Note on static state: $connectionManager and $observers below ARE
 * static properties on a class that instances get created from
 * frequently — but they hold framework-level configuration set once at
 * boot, never per-request mutable data, so they're exempt from the same
 * "static state leaks across requests" concern the state-safety layer
 * (build spec §4) targets. The custom PHPStan rule from step 3 only
 * flags static properties inside app/Modules for exactly this reason —
 * this file lives under Spinx\, deliberately outside that rule's scope.
 */
abstract class Model
{
    protected static string $table = '';
    protected static string $primaryKey = 'id';

    /** @var string[] Attribute keys allowed to be mass-assigned via fill()/create(). Empty = allow all. */
    protected array $fillable = [];

    /** @var array<string, string> Attribute key => cast type ('int', 'float', 'bool', 'array', 'json', 'datetime') */
    protected array $casts = [];

    protected bool $timestamps = true;
    protected bool $softDeletes = false;

    /** @var array<string, mixed> */
    protected array $attributes = [];

    /** @var array<string, mixed> Snapshot taken at hydration/save time, used to compute dirty attributes */
    protected array $original = [];

    /** @var array<string, mixed> Cached relation results, populated lazily or via with() */
    protected array $relationsCache = [];

    protected bool $exists = false;

    private static ?ConnectionManager $connectionManager = null;

    /** @var array<class-string, list<class-string>> */
    private static array $observers = [];

    /** @param array<string, mixed> $attributes */
    final public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
    }

    // ---------------------------------------------------------------
    // Boot wiring
    // ---------------------------------------------------------------

    /** Called once during Kernel::boot() — see Kernel.php. */
    public static function setConnectionManager(ConnectionManager $manager): void
    {
        self::$connectionManager = $manager;
    }

    protected static function connection(): Connection
    {
        if (self::$connectionManager === null) {
            throw new \RuntimeException(
                'No database connection manager configured. This should be wired automatically by Kernel::boot() — ' .
                'if you\'re seeing this in a unit test, call Model::setConnectionManager() manually first.'
            );
        }

        return self::$connectionManager->get();
    }

    // ---------------------------------------------------------------
    // Table / query access
    // ---------------------------------------------------------------

    public static function table(): string
    {
        if (static::$table !== '') {
            return static::$table;
        }

        // Convention: short class name, snake_cased, naively pluralized
        // with a trailing 's'. Set protected static $table explicitly for
        // irregular plurals ("Person" -> "people", etc.).
        $short = (new \ReflectionClass(static::class))->getShortName();
        $snake = self::toSnakeCase($short);

        return $snake . 's';
    }

    public static function query(): QueryBuilder
    {
        return new QueryBuilder(static::connection(), static::table(), static::class);
    }

    /** Raw query against an arbitrary table (e.g. a pivot table), returning plain arrays rather than model instances. */
    public static function rawQuery(string $table): QueryBuilder
    {
        return new QueryBuilder(static::connection(), $table);
    }

    public static function find(int|string $id): ?static
    {
        return static::query()->where(static::$primaryKey, $id)->first();
    }

    public static function findOrFail(int|string $id): static
    {
        $model = static::find($id);

        if ($model === null) {
            throw new \RuntimeException(sprintf('%s with %s = %s not found.', static::class, static::$primaryKey, $id));
        }

        return $model;
    }

    /** @return array<int, static> */
    public static function all(): array
    {
        return static::query()->get();
    }

    public static function paginate(int $perPage = 15, int $page = 1): Paginator
    {
        return static::query()->paginate($perPage, $page);
    }

    public static function cursorPaginate(
        int $perPage = 15,
        string $cursorCol = 'id',
        ?string $cursor = null,
        string $direction = 'asc',
    ): \Spinx\Database\Pagination\CursorPaginator {
        return static::query()->cursorPaginate($perPage, $cursorCol, $cursor, $direction);
    }


    /** @param array<string, mixed> $attributes */
    public static function create(array $attributes): static
    {
        $model = new static($attributes);
        $model->save();

        return $model;
    }

    /**
     * Finds the first row matching $attributes; creates one (merged with
     * $values) if none exists. The search-and-create is not atomic —
     * two concurrent requests can both miss the find and both attempt a
     * create, one of which may then fail on a unique constraint at the
     * database level. That failure is the correct behavior (it means the
     * constraint did its job); this method doesn't retry or swallow it.
     *
     * @param array<string, mixed> $attributes
     * @param array<string, mixed> $values
     */
    public static function firstOrCreate(array $attributes, array $values = []): static
    {
        $existing = static::firstWhere($attributes);

        return $existing ?? static::create([...$attributes, ...$values]);
    }

    /** Like firstOrCreate(), but returns an unsaved instance instead of persisting — call ->save() yourself. */
    public static function firstOrNew(array $attributes, array $values = []): static
    {
        return static::firstWhere($attributes) ?? new static([...$attributes, ...$values]);
    }

    /**
     * Finds the first row matching $attributes and updates it with
     * $values; creates a new row (merged) if none exists. Same
     * non-atomicity caveat as firstOrCreate().
     *
     * @param array<string, mixed> $attributes
     * @param array<string, mixed> $values
     */
    public static function updateOrCreate(array $attributes, array $values = []): static
    {
        $existing = static::firstWhere($attributes);

        if ($existing !== null) {
            $existing->update($values);

            return $existing;
        }

        return static::create([...$attributes, ...$values]);
    }

    /**
     * Platform-aware single-row upsert — atomic at the database level
     * (unlike updateOrCreate which is two round-trips with a race window).
     *
     * Chooses SQL based on the underlying DBAL platform:
     *   - MySQL / MariaDB → INSERT … ON DUPLICATE KEY UPDATE col = VALUES(col)
     *   - PostgreSQL / SQLite → INSERT … ON CONFLICT (uniqueColumns) DO UPDATE SET col = EXCLUDED.col
     *
     * @param array<string, mixed> $values         Full row to insert (including unique key columns)
     * @param string[]             $uniqueColumns  Column(s) forming the conflict target (must match a UNIQUE/PK constraint)
     * @param string[]|null        $updateColumns  Which columns to update on conflict; null means update all non-unique columns
     *
     * @throws \RuntimeException If the current platform is unsupported
     */
    public static function upsert(array $values, array $uniqueColumns, ?array $updateColumns = null): int
    {
        $connection = static::connection();
        $platform   = $connection->getDatabasePlatform();

        // Determine which columns to update on conflict.
        $updateCols = $updateColumns ?? array_values(array_diff(array_keys($values), $uniqueColumns));

        if ($updateCols === []) {
            // Nothing to update — just a plain insert, ignore duplicates.
            $connection->insert(static::table(), $values);

            return 0;
        }

        $columnList = implode(', ', array_keys($values));
        $paramNames = array_map(static fn ($k) => ':' . $k, array_keys($values));
        $paramList  = implode(', ', $paramNames);

        if ($platform instanceof \Doctrine\DBAL\Platforms\MySQLPlatform) {
            // MySQL / MariaDB: VALUES() is the standard way to reference the
            // incoming row's value inside ON DUPLICATE KEY UPDATE.
            $updateClauses = implode(', ', array_map(
                static fn ($col) => "{$col} = VALUES({$col})",
                $updateCols
            ));
            $sql = "INSERT INTO " . static::table() . " ({$columnList}) VALUES ({$paramList}) ON DUPLICATE KEY UPDATE {$updateClauses}";
        } elseif ($platform instanceof \Doctrine\DBAL\Platforms\PostgreSQLPlatform
            || $platform instanceof \Doctrine\DBAL\Platforms\SqlitePlatform
        ) {
            // PostgreSQL / SQLite: standard SQL MERGE / UPSERT using EXCLUDED pseudo-table.
            $conflictTarget = implode(', ', $uniqueColumns);
            $updateClauses  = implode(', ', array_map(
                static fn ($col) => "{$col} = EXCLUDED.{$col}",
                $updateCols
            ));
            $sql = "INSERT INTO " . static::table() . " ({$columnList}) VALUES ({$paramList}) ON CONFLICT ({$conflictTarget}) DO UPDATE SET {$updateClauses}";
        } else {
            throw new \RuntimeException(sprintf(
                'Model::upsert() is not supported on platform "%s". Use updateOrCreate() instead.',
                $platform::class
            ));
        }

        return (int) $connection->executeStatement($sql, $values);
    }

    /**
     * SELECT FOR UPDATE + transaction — fetches the row by primary key
     * with an exclusive row lock, then passes it to $callback. Anything
     * the callback does (including saving the model) runs inside the same
     * transaction. The lock is released when the transaction commits.
     *
     * Correct pattern for preventing lost-update races on a single row:
     *
     *   Order::atomic($id, function (Order $order): void {
     *       $order->update(['status' => 'processing']);
     *   });
     *
     * @throws \RuntimeException If the row is not found
     */
    public static function atomic(int|string $id, \Closure $callback): void
    {
        $connection = static::connection();

        $connection->transactional(static function () use ($id, $callback, $connection): void {
            $sql  = sprintf(
                'SELECT * FROM %s WHERE %s = :id LIMIT 1 FOR UPDATE',
                static::table(),
                static::$primaryKey
            );
            $row = $connection->fetchAssociative($sql, ['id' => $id]);

            if ($row === false) {
                throw new \RuntimeException(sprintf(
                    '%s with %s = %s not found in atomic().',
                    static::class, static::$primaryKey, $id
                ));
            }

            $model = static::hydrate($row);
            $callback($model);
        });
    }


    /** @param array<string, mixed> $attributes */
    private static function firstWhere(array $attributes): ?static
    {
        $query = static::query();

        foreach ($attributes as $column => $value) {
            $query->where($column, $value);
        }

        return $query->first();
    }

    /**
     * Builds a model instance directly from a database row (already
     * persisted), bypassing fill()'s fillable guard — used by
     * QueryBuilder when hydrating query results, where every column is
     * expected to be trustworthy since it came from the database itself.
     *
     * @param array<string, mixed> $row
     */
    public static function hydrate(array $row): static
    {
        $model = new static();
        $model->attributes = $row;
        $model->original = $row;
        $model->exists = true;

        return $model;
    }

    // ---------------------------------------------------------------
    // Attribute access, casting, mass assignment
    // ---------------------------------------------------------------

    /** @param array<string, mixed> $attributes */
    public function fill(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            if ($this->fillable !== [] && !in_array($key, $this->fillable, true)) {
                continue; // Silently ignored, mirroring Eloquent's guarded-by-default mass-assignment behavior.
            }

            $this->setAttribute($key, $value);
        }

        return $this;
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $this->castForStorage($key, $value);
    }

    public function getAttribute(string $key): mixed
    {
        return $this->castFromStorage($key, $this->attributes[$key] ?? null);
    }

    public function hasAttribute(string $key): bool
    {
        return array_key_exists($key, $this->attributes);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $result = [];
        foreach (array_keys($this->attributes) as $key) {
            $result[$key] = $this->getAttribute($key);
        }

        return $result;
    }

    /** @param string[] $keys @return array<string, mixed> */
    public function only(array $keys): array
    {
        return array_intersect_key($this->toArray(), array_flip($keys));
    }

    /** @param string[] $keys @return array<string, mixed> */
    public function except(array $keys): array
    {
        return array_diff_key($this->toArray(), array_flip($keys));
    }

    /**
     * Returns a NEW instance re-fetched from the database — this
     * instance's own attributes are untouched. Returns null if the row
     * no longer exists (e.g. deleted by another request since this
     * instance was loaded).
     */
    public function fresh(): ?static
    {
        if (!$this->exists) {
            return null;
        }

        return static::find($this->attributes[static::$primaryKey]);
    }

    /**
     * Re-fetches this exact instance's attributes from the database IN
     * PLACE — unlike fresh(), $this is mutated, not replaced. Throws if
     * the row no longer exists, since silently leaving stale data in
     * place would be worse than a clear failure.
     */
    public function refresh(): static
    {
        $fresh = $this->fresh();

        if ($fresh === null) {
            throw new \RuntimeException(sprintf(
                '%s with %s = %s no longer exists — cannot refresh().',
                static::class,
                static::$primaryKey,
                $this->attributes[static::$primaryKey] ?? 'null'
            ));
        }

        $this->attributes = $fresh->attributes;
        $this->original = $fresh->attributes;
        $this->relationsCache = [];

        return $this;
    }

    public function __get(string $key): mixed
    {
        if ($this->hasAttribute($key)) {
            return $this->getAttribute($key);
        }

        if (array_key_exists($key, $this->relationsCache)) {
            return $this->relationsCache[$key];
        }

        if (method_exists($this, $key)) {
            $relation = $this->{$key}();

            if ($relation instanceof Relation) {
                return $this->relationsCache[$key] = $relation->getResults();
            }
        }

        return null;
    }

    public function __set(string $key, mixed $value): void
    {
        $this->setAttribute($key, $value);
    }

    public function __isset(string $key): bool
    {
        return $this->hasAttribute($key) || array_key_exists($key, $this->relationsCache);
    }

    /** Used by Relation::loadInto() to populate eager-loaded results. */
    public function setRelation(string $name, mixed $value): void
    {
        $this->relationsCache[$name] = $value;
    }

    /**
     * Bridges the visibility gap for eager loading: relation-defining
     * methods (customer(), items(), etc.) are documented and used
     * throughout this framework as protected — an implementation detail,
     * not public API — but QueryBuilder::applyEagerLoads() is a
     * different class and can't call a protected method directly.
     * Calling $this->{$name}() from HERE, inside Model's own class
     * scope, satisfies PHP's visibility rules cleanly without having to
     * make every relation method public just for the framework's own
     * internal use. Found and fixed via a real eager-loading test
     * against an actual protected relation method — every earlier test
     * of with() had exercised Relation objects directly, never through
     * this exact call path, which is why this went undetected until then.
     */
    public function resolveRelationMethod(string $name): Relation
    {
        return $this->{$name}();
    }

    private function castFromStorage(string $key, mixed $value): mixed
    {
        if ($value === null || !isset($this->casts[$key])) {
            return $value;
        }

        return match ($this->casts[$key]) {
            'int', 'integer' => (int) $value,
            'float', 'double' => (float) $value,
            'bool', 'boolean' => (bool) $value,
            'array', 'json' => is_string($value) ? json_decode($value, true) : $value,
            'datetime' => $value instanceof \DateTimeImmutable ? $value : new \DateTimeImmutable((string) $value),
            default => $value,
        };
    }

    private function castForStorage(string $key, mixed $value): mixed
    {
        if ($value === null || !isset($this->casts[$key])) {
            return $value;
        }

        return match ($this->casts[$key]) {
            'array', 'json' => is_array($value) ? json_encode($value, JSON_THROW_ON_ERROR) : $value,
            'datetime' => $value instanceof \DateTimeInterface ? $value->format('Y-m-d H:i:s') : $value,
            'bool', 'boolean' => (int) (bool) $value,
            default => $value,
        };
    }

    // ---------------------------------------------------------------
    // Persistence
    // ---------------------------------------------------------------

    public function isDirty(): bool
    {
        return $this->dirtyAttributes() !== [];
    }

    /** @return array<string, mixed> */
    private function dirtyAttributes(): array
    {
        $dirty = [];

        foreach ($this->attributes as $key => $value) {
            if (!array_key_exists($key, $this->original) || $this->original[$key] !== $value) {
                $dirty[$key] = $value;
            }
        }

        return $dirty;
    }

    public function save(): bool
    {
        $isCreating = !$this->exists;
        $this->fireEvent($isCreating ? 'creating' : 'updating');

        if ($this->timestamps) {
            $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

            if ($isCreating) {
                $this->attributes['created_at'] ??= $now;
            }

            $this->attributes['updated_at'] = $now;
        }

        if ($isCreating) {
            $id = static::query()->insert($this->attributes);
            $this->attributes[static::$primaryKey] ??= $id;
            $this->exists = true;
        } else {
            $dirty = $this->dirtyAttributes();

            if ($dirty !== []) {
                static::query()->where(static::$primaryKey, $this->attributes[static::$primaryKey])->update($dirty);
            }
        }

        $this->original = $this->attributes;
        $this->fireEvent($isCreating ? 'created' : 'updated');

        return true;
    }

    /** @param array<string, mixed> $attributes */
    public function update(array $attributes): bool
    {
        $this->fill($attributes);

        return $this->save();
    }

    public function delete(): bool
    {
        $this->fireEvent('deleting');

        $primaryValue = $this->attributes[static::$primaryKey] ?? null;

        if ($this->softDeletes) {
            $deletedAt = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
            $this->attributes['deleted_at'] = $deletedAt;
            $result = static::query()->where(static::$primaryKey, $primaryValue)->update(['deleted_at' => $deletedAt]);
        } else {
            $result = static::query()->where(static::$primaryKey, $primaryValue)->delete();
        }

        $this->fireEvent('deleted');

        return $result > 0;
    }

    // ---------------------------------------------------------------
    // Model events / observers
    // ---------------------------------------------------------------

    /** @param class-string $observerClass Instance methods matching event names (creating, created, updating, updated, deleting, deleted) are called automatically. */
    public static function observe(string $observerClass): void
    {
        self::$observers[static::class][] = $observerClass;
    }

    private function fireEvent(string $event): void
    {
        foreach (self::$observers[static::class] ?? [] as $observerClass) {
            $observer = new $observerClass();

            if (method_exists($observer, $event)) {
                $observer->{$event}($this);
            }
        }
    }

    // ---------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------

    /** @param class-string<Model> $related */
    protected function hasOne(string $related, ?string $foreignKey = null, ?string $localKey = null): HasOne
    {
        return new HasOne($this, $related, $foreignKey ?? $this->defaultForeignKey(), $localKey ?? static::$primaryKey);
    }

    /** @param class-string<Model> $related */
    protected function hasMany(string $related, ?string $foreignKey = null, ?string $localKey = null): HasMany
    {
        return new HasMany($this, $related, $foreignKey ?? $this->defaultForeignKey(), $localKey ?? static::$primaryKey);
    }

    /** @param class-string<Model> $related */
    protected function belongsTo(string $related, ?string $foreignKey = null, ?string $ownerKey = null): BelongsTo
    {
        $foreignKey ??= self::toSnakeCase((new \ReflectionClass($related))->getShortName()) . '_id';

        return new BelongsTo($this, $related, $foreignKey, $ownerKey ?? 'id');
    }

    /** @param class-string<Model> $related */
    protected function belongsToMany(
        string $related,
        ?string $pivotTable = null,
        ?string $foreignPivotKey = null,
        ?string $relatedPivotKey = null,
    ): BelongsToMany {
        return new BelongsToMany(
            $this,
            $related,
            $pivotTable ?? $this->defaultPivotTable($related),
            $foreignPivotKey ?? $this->defaultForeignKey(),
            $relatedPivotKey ?? self::toSnakeCase((new \ReflectionClass($related))->getShortName()) . '_id',
        );
    }

    /** @param class-string<Model> $related */
    protected function morphMany(string $related, string $morphName): MorphMany
    {
        return new MorphMany($this, $related, $morphName . '_id', $morphName . '_type');
    }

    protected function morphTo(string $morphName = 'morphable'): MorphTo
    {
        return new MorphTo($this, $morphName . '_id', $morphName . '_type');
    }

    protected function defaultForeignKey(): string
    {
        $short = (new \ReflectionClass(static::class))->getShortName();

        return self::toSnakeCase($short) . '_id';
    }

    /** @param class-string<Model> $related */
    protected function defaultPivotTable(string $related): string
    {
        $names = [
            self::toSnakeCase((new \ReflectionClass(static::class))->getShortName()),
            self::toSnakeCase((new \ReflectionClass($related))->getShortName()),
        ];
        sort($names);

        return implode('_', $names);
    }

    protected static function toSnakeCase(string $studlyCase): string
    {
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $studlyCase));
    }
}
