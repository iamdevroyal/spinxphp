<?php

declare(strict_types=1);

namespace Spinx\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder as DbalQueryBuilder;
use Spinx\Database\Schema\SchemaCache;

/**
 * Eloquent-style fluent query builder over Doctrine DBAL (build spec §7).
 * Doctrine ORM's full UnitOfWork was explicitly ruled out (see build spec
 * §7.1) because it isn't coroutine-safe — this class is deliberately a
 * thin, stateless-per-call wrapper instead: every method mutates and
 * returns $this, but nothing here is shared or cached across requests.
 *
 * with()-based eager loading issues one batched WHERE IN query per
 * relation regardless of result set size — see
 * Spinx\Database\Relations\Relation::eagerLoad() and its subclasses.
 * Verified directly against real batching behavior, not just correctness
 * — see the test suite for a case asserting exactly one query fires
 * across five loaded rows.
 */
final class QueryBuilder
{
    private DbalQueryBuilder $query;
    private int $paramCounter = 0;

    /** @var array<int, string> Relation method names requested via with() */
    private array $eagerLoads = [];

    /**
     * Tracks pending condition status set by when().
     * true  = condition met, then() will apply its callback.
     * false = condition not met, then() skips, else() applies.
     * null  = no pending condition (reset after then()/else()).
     */
    private ?bool $pendingConditionStatus = null;

    /**
     * Context values populated by where() calls and used by the
     * three-argument form of when() for column-value comparisons.
     * Keys are column names, values are the bound query values.
     *
     * @var array<string, mixed>
     */
    private array $attributes = [];

    public function __construct(
        private readonly Connection $connection,
        private readonly string $table,
        /** @var class-string<Model>|null Null for raw queries (e.g. pivot tables) — rows returned as plain arrays */
        private readonly ?string $modelClass = null,
    ) {
        $this->query = $this->connection->createQueryBuilder()->select('*')->from($this->table);
    }

    public function where(string $column, mixed $operatorOrValue, mixed $value = null): static
    {
        [$operator, $boundValue] = func_num_args() === 2 ? ['=', $operatorOrValue] : [$operatorOrValue, $value];
        $param = $this->bindParam($boundValue);
        $this->query->andWhere("{$column} {$operator} {$param}");

        // Track in $attributes so the three-argument form of when() can
        // evaluate column comparisons against already-constrained values.
        $this->attributes[$column] = $boundValue;

        return $this;
    }

    public function orWhere(string $column, mixed $operatorOrValue, mixed $value = null): static
    {
        [$operator, $boundValue] = func_num_args() === 2 ? ['=', $operatorOrValue] : [$operatorOrValue, $value];
        $param = $this->bindParam($boundValue);
        $this->query->orWhere("{$column} {$operator} {$param}");

        return $this;
    }

    public function whereNull(string $column): static
    {
        $this->query->andWhere("{$column} IS NULL");

        return $this;
    }

    public function whereNotNull(string $column): static
    {
        $this->query->andWhere("{$column} IS NOT NULL");

        return $this;
    }

    public function whereBetween(string $column, mixed $min, mixed $max): static
    {
        $minParam = $this->bindParam($min);
        $maxParam = $this->bindParam($max);
        $this->query->andWhere("{$column} BETWEEN {$minParam} AND {$maxParam}");

        return $this;
    }

    public function whereNotBetween(string $column, mixed $min, mixed $max): static
    {
        $minParam = $this->bindParam($min);
        $maxParam = $this->bindParam($max);
        $this->query->andWhere("{$column} NOT BETWEEN {$minParam} AND {$maxParam}");

        return $this;
    }

    /** @param scalar[] $values */
    public function whereIn(string $column, array $values): static
    {
        if ($values === []) {
            // No possible match — short-circuit to a condition that's
            // always false rather than emitting invalid SQL ("IN ()").
            $this->query->andWhere('1 = 0');

            return $this;
        }

        $placeholders = array_map(fn ($value) => $this->bindParam($value), $values);
        $this->query->andWhere(sprintf('%s IN (%s)', $column, implode(', ', $placeholders)));

        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): static
    {
        $this->query->addOrderBy($column, $direction);

        return $this;
    }

    /** orderBy($column, 'DESC') shorthand — defaults to created_at, matching Eloquent's latest(). */
    public function latest(string $column = 'created_at'): static
    {
        return $this->orderBy($column, 'DESC');
    }

    /** orderBy($column, 'ASC') shorthand — defaults to created_at, matching Eloquent's oldest(). */
    public function oldest(string $column = 'created_at'): static
    {
        return $this->orderBy($column, 'ASC');
    }

    public function groupBy(string ...$columns): static
    {
        $this->query->groupBy(implode(', ', $columns));

        return $this;
    }

    public function having(string $expression): static
    {
        $this->query->andHaving($expression);

        return $this;
    }

    /** Eager-load the named relations — see Spinx\Database\Relations\Relation::eagerLoad() for the batching strategy each type uses. */
    public function with(string ...$relations): static
    {
        array_push($this->eagerLoads, ...$relations);

        return $this;
    }

    // ---------------------------------------------------------------
    // Column selection
    // ---------------------------------------------------------------

    /**
     * SELECT only the named columns, replacing the default SELECT *.
     *
     *   User::query()->selectWith('id', 'name', 'email')->get();
     */
    public function selectWith(string ...$columns): static
    {
        if ($columns !== []) {
            $this->query->select(...$columns);
        }

        return $this;
    }

    /**
     * SELECT all columns except the named ones.
     *
     * Column list comes from SchemaCache — run `spinx schema:compile` once
     * after migrations to generate storage/cache/schema_columns.php.
     * If the cache isn't loaded yet (file not compiled), emits an E_USER_WARNING
     * and falls back to SELECT *, which is safe but defeats the purpose.
     *
     *   User::query()->selectWithout('password', 'remember_token')->get();
     */
    public function selectWithout(string ...$columns): static
    {
        $all = SchemaCache::columnsFor($this->table);

        if ($all === []) {
            trigger_error(
                "[Spinx] selectWithout('{$this->table}') called but the schema cache is empty. "
                . 'Run `php spinx schema:compile` after your migrations to generate storage/cache/schema_columns.php.',
                E_USER_WARNING,
            );

            // Fall back to SELECT * — query still executes, just returns all columns.
            return $this;
        }

        $selected = array_values(array_diff($all, $columns));

        if ($selected === []) {
            // Every column was excluded — SELECT 1 to avoid invalid SQL.
            $this->query->select('1');
        } else {
            $this->query->select(...$selected);
        }

        return $this;
    }

    // ---------------------------------------------------------------
    // Conditional query building — when() / then() / else()
    // ---------------------------------------------------------------

    /**
     * Stores a pending condition that the subsequent then() / else() will act on.
     *
     * Two call signatures (dispatched by func_num_args()):
     *
     * **1. Single boolean argument (build-time evaluation):**
     *   PHP evaluates the expression before passing it — when() just stores the result.
     *
     *     Order::query()
     *         ->when($isAdmin)
     *         ->then(fn($q) => $q->where('deleted_at', 'IS', null))
     *         ->get();
     *
     * **2. Three arguments — column, operator, value (query-context evaluation):**
     *   Reads the column's value from $this->attributes (populated by prior where() calls)
     *   and evaluates it against the given operator and value.
     *
     *     Order::query()
     *         ->where('total', '>', 100)
     *         ->when('total', '>', 600)
     *         ->then(fn($q) => $q->where('flagged', true))
     *         ->else(fn($q) => $q->orderBy('total'))
     *         ->get();
     *
     * NOTE: The triple-argument form requires that the column's value was already
     * bound via a prior where() call so $attributes['column'] is populated.
     * When in doubt, use the single-boolean form — it is always unambiguous.
     */
    public function when(mixed $columnOrBool, ?string $operator = null, mixed $value = null): static
    {
        if (func_num_args() === 1) {
            // Single-argument: PHP already evaluated the expression.
            $this->pendingConditionStatus = (bool) $columnOrBool;

            return $this;
        }

        // Three-argument: read column from $attributes and compare.
        $contextValue = $this->attributes[(string) $columnOrBool] ?? null;
        $this->pendingConditionStatus = $this->evaluateCondition($contextValue, (string) $operator, $value);

        return $this;
    }

    /**
     * Applies $callback to $this only if the preceding when() evaluated to true.
     * Resets the pending condition afterwards so the next when()->then() pair starts fresh.
     */
    public function then(\Closure $callback): static
    {
        if ($this->pendingConditionStatus === true) {
            $callback($this);
        }

        $this->pendingConditionStatus = null;

        return $this;
    }

    /**
     * Applies $callback to $this only if the preceding when() evaluated to false.
     * Resets the pending condition afterwards.
     */
    public function else(\Closure $callback): static
    {
        if ($this->pendingConditionStatus === false) {
            $callback($this);
        }

        $this->pendingConditionStatus = null;

        return $this;
    }

    /** Alias for else() — some developers prefer the word. */
    public function otherwise(\Closure $callback): static
    {
        return $this->else($callback);
    }

    /**
     * Evaluates $actual against $operator and $expected.
     * Used by the three-argument form of when().
     */
    private function evaluateCondition(mixed $actual, string $operator, mixed $expected): bool
    {
        return match ($operator) {
            '=', '=='  => $actual == $expected,
            '==='      => $actual === $expected,
            '>'        => $actual > $expected,
            '<'        => $actual < $expected,
            '>='       => $actual >= $expected,
            '<='       => $actual <= $expected,
            '!=', '<>' => $actual != $expected,
            '!=='      => $actual !== $expected,
            default    => false,
        };
    }

    public function limit(int $limit): static
    {
        $this->query->setMaxResults($limit);

        return $this;
    }

    public function offset(int $offset): static
    {
        $this->query->setFirstResult($offset);

        return $this;
    }

    /** @return array<int, mixed> Model instances if modelClass is set, otherwise raw associative row arrays */
    public function get(): array
    {
        $rows = $this->query->executeQuery()->fetchAllAssociative();
        $results = $this->hydrate($rows);
        $this->applyEagerLoads($results);

        return $results;
    }

    public function first(): mixed
    {
        $this->limit(1);
        $results = $this->get();

        return $results[0] ?? null;
    }

    /** @throws \RuntimeException If no matching row exists */
    public function firstOrFail(): mixed
    {
        $result = $this->first();

        if ($result === null) {
            throw new \RuntimeException(sprintf('No matching row found in "%s" for this query.', $this->table));
        }

        return $result;
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }

    public function doesntExist(): bool
    {
        return !$this->exists();
    }

    public function count(): int
    {
        $countQuery = clone $this->query;
        $countQuery->select('COUNT(*) AS aggregate')->setMaxResults(null)->setFirstResult(0);

        return (int) $countQuery->executeQuery()->fetchOne();
    }

    /** Single column across every matching row — e.g. pluck('email') -> ['a@x.com', 'b@x.com']. Casts still apply when modelClass is set. */
    public function pluck(string $column): array
    {
        return array_map(
            fn ($row) => $this->modelClass === null ? ($row[$column] ?? null) : $row->getAttribute($column),
            $this->get()
        );
    }

    /** Single column from the first matching row, or null if no row matches. */
    public function value(string $column): mixed
    {
        $first = $this->first();

        if ($first === null) {
            return null;
        }

        return $this->modelClass === null ? ($first[$column] ?? null) : $first->getAttribute($column);
    }

    /**
     * Processes results in fixed-size batches rather than loading
     * everything into memory at once — for large tables. $callback
     * receives each batch (an array of rows/models); return false from
     * it to stop early.
     */
    public function chunk(int $size, callable $callback): void
    {
        $page = 1;

        do {
            $batch = (clone $this)->limit($size)->offset(($page - 1) * $size)->get();

            if ($batch === []) {
                break;
            }

            if ($callback($batch) === false) {
                return;
            }

            $page++;
        } while (count($batch) === $size);
    }

    public function paginate(int $perPage = 15, int $page = 1): Paginator
    {
        $total = $this->count();
        $this->limit($perPage)->offset(($page - 1) * $perPage);
        $items = $this->get();

        return new Paginator($items, $total, $perPage, $page);
    }

    /** @param array<string, mixed> $attributes @return int|string Last insert ID */
    public function insert(array $attributes): int|string
    {
        $this->connection->insert($this->table, $attributes);

        return $this->connection->lastInsertId();
    }

    /** @param array<string, mixed> $attributes */
    public function update(array $attributes): int
    {
        $updateQuery = $this->connection->createQueryBuilder()->update($this->table);

        foreach ($attributes as $column => $value) {
            $param = $this->bindParamOn($updateQuery, $value);
            $updateQuery->set($column, $param);
        }

        $this->copyWhereOnto($updateQuery);

        return (int) $updateQuery->executeStatement();
    }

    public function delete(): int
    {
        $deleteQuery = $this->connection->createQueryBuilder()->delete($this->table);
        $this->copyWhereOnto($deleteQuery);

        return (int) $deleteQuery->executeStatement();
    }

    public function increment(string $column, int|float $amount = 1): int
    {
        return $this->incrementOrDecrement($column, $amount, '+');
    }

    public function decrement(string $column, int|float $amount = 1): int
    {
        return $this->incrementOrDecrement($column, $amount, '-');
    }

    private function incrementOrDecrement(string $column, int|float $amount, string $operator): int
    {
        $updateQuery = $this->connection->createQueryBuilder()->update($this->table);
        $param = $this->bindParamOn($updateQuery, $amount);
        // Raw SQL expression as the SET value (standard DBAL usage) —
        // "column = column + :amount" computed atomically in the
        // database itself, not read-then-write from PHP, which avoids a
        // lost-update race between two concurrent requests incrementing
        // the same row.
        $updateQuery->set($column, "{$column} {$operator} {$param}");
        $this->copyWhereOnto($updateQuery);

        return (int) $updateQuery->executeStatement();
    }

    /**
     * update() and delete() build fresh DBAL query builders (DBAL doesn't
     * support converting a SELECT builder into an UPDATE/DELETE in
     * place), so the WHERE clause and its bound parameters have to be
     * copied across manually to preserve whatever constraints where()
     * calls already added.
     */
    private function copyWhereOnto(DbalQueryBuilder $target): void
    {
        $where = $this->query->getQueryPart('where');

        if ($where === null) {
            return;
        }

        $target->where($where);

        foreach ($this->query->getParameters() as $key => $value) {
            $target->setParameter($key, $value);
        }
    }

    private function bindParam(mixed $value): string
    {
        return $this->bindParamOn($this->query, $value);
    }

    private function bindParamOn(DbalQueryBuilder $queryBuilder, mixed $value): string
    {
        $name = 'p' . $this->paramCounter++;
        $queryBuilder->setParameter($name, $value);

        return ':' . $name;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, mixed>
     */
    private function hydrate(array $rows): array
    {
        if ($this->modelClass === null) {
            return $rows;
        }

        $modelClass = $this->modelClass;

        return array_map(static fn (array $row) => $modelClass::hydrate($row), $rows);
    }

    /**
     * Real batched eager loading: for each relation named via with(),
     * builds ONE relation instance off the first model (relation
     * definitions — foreign key, local key, etc. — are identical across
     * every model of the same class, so any single model's relation
     * instance carries what's needed), then calls its eagerLoad() once
     * for the whole batch. Each relation type owns its own batching
     * strategy — see Spinx\Database\Relations\Relation and its
     * subclasses — instead of this method issuing one query per row.
     *
     * @param array<int, mixed> $models
     */
    private function applyEagerLoads(array $models): void
    {
        if ($models === [] || $this->eagerLoads === [] || $this->modelClass === null) {
            return;
        }

        foreach ($this->eagerLoads as $relationName) {
            $firstModel = $models[0];

            if (!$firstModel instanceof Model || !method_exists($firstModel, $relationName)) {
                throw new \RuntimeException(sprintf(
                    'Model %s has no relation method "%s" for with(\'%2$s\').',
                    $this->modelClass,
                    $relationName
                ));
            }

            // resolveRelationMethod(), not $firstModel->{$relationName}()
            // directly — relation-defining methods are protected by
            // convention throughout this framework, and QueryBuilder
            // isn't part of Model's class hierarchy, so a direct dynamic
            // call would fail visibility checks. See that method's own
            // docblock on Model for the full story, including a real bug
            // this fixed.
            $relation = $firstModel->resolveRelationMethod($relationName);
            $relation->eagerLoad($models, $relationName);
        }
    }
}
