<?php

declare(strict_types=1);

namespace Spinx\Database\Schema;

/**
 * Immutable, boot-time schema column map.
 *
 * Populated once at Kernel::boot() from the pre-compiled file written by
 * SchemaCompiler (see Spinx\Database\Schema\SchemaCompiler). Workers read
 * this file entirely into OpCache memory — zero database calls after boot,
 * no lock risk, no per-request overhead.
 *
 * The column map is intentionally a plain PHP array rather than a class
 * with a query interface. This keeps it trivially serialisable,
 * OpCache-friendly, and free of any Doctrine DBAL dependency at read time.
 *
 * Usage (inside QueryBuilder::selectWithout):
 *
 *   $all    = SchemaCache::columnsFor('orders');
 *   $select = array_diff($all, ['password', 'remember_token']);
 *   $query->select(implode(', ', $select));
 */
final class SchemaCache
{
    /** @var array<string, string[]>|null table_name => [column_name, ...] */
    private static ?array $map = null;

    private static string $compiledPath = '';

    /**
     * Called once at Kernel::boot() — loads the pre-compiled schema file
     * if it exists. Silently skips if the file hasn't been generated yet
     * (selectWithout will fall back to an empty column list and emit a
     * warning, which surfaces the need to run `spinx schema:compile`).
     */
    public static function boot(string $projectRoot): void
    {
        self::$compiledPath = $projectRoot . '/storage/cache/schema_columns.php';

        if (is_file(self::$compiledPath)) {
            /** @var array<string, string[]> $data */
            $data      = require self::$compiledPath;
            self::$map = is_array($data) ? $data : [];
        } else {
            self::$map = [];
        }
    }

    /**
     * Returns the ordered column list for the given table, or an empty
     * array if the table isn't in the map (file not compiled yet, or
     * table genuinely has no columns — both are treated the same way;
     * callers are responsible for surfacing a useful error message).
     *
     * @return string[]
     */
    public static function columnsFor(string $table): array
    {
        return self::$map[$table] ?? [];
    }

    /** Returns true if the column map has been loaded and contains at least one table. */
    public static function isLoaded(): bool
    {
        return self::$map !== null && self::$map !== [];
    }

    /** Path written to by SchemaCompiler, read from by boot(). */
    public static function compiledPath(): string
    {
        return self::$compiledPath;
    }
}
