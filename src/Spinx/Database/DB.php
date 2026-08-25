<?php

declare(strict_types=1);

namespace Spinx\Database;

use Doctrine\DBAL\Connection;
use Spinx\Database\Connection\ConnectionManager;

/**
 * Static-resolver façade for raw database access — complements Model's
 * active-record API for cases where a model class isn't appropriate:
 * reporting queries that span multiple tables, DDL in migrations,
 * or one-off statements that don't map to an entity.
 *
 * Uses the same static-resolver pattern as Model::setConnectionManager()
 * so it plugs into the same Kernel::boot() wiring without any extra DI
 * registration. One call at boot, then available everywhere.
 *
 * Usage:
 *   DB::transaction(function (Connection $conn): void {
 *       $conn->executeStatement('DELETE FROM sessions WHERE expires_at < NOW()');
 *       $conn->executeStatement('UPDATE users SET login_count = login_count + 1 WHERE id = :id', ['id' => $userId]);
 *   });
 *
 *   $rows = DB::select('SELECT id, name FROM orders WHERE status = :s', ['s' => 'pending']);
 *   DB::statement('TRUNCATE abandoned_carts');
 */
final class DB
{
    private static ?ConnectionManager $connectionManager = null;

    /** Called once at Kernel::boot() — same pattern as Model::setConnectionManager(). */
    public static function setConnectionManager(ConnectionManager $manager): void
    {
        self::$connectionManager = $manager;
    }

    public static function connection(): Connection
    {
        if (self::$connectionManager === null) {
            throw new \RuntimeException(
                'DB::connection() called before Kernel::boot(). ' .
                'DB is wired automatically — if you are in a unit test, call DB::setConnectionManager() manually first.'
            );
        }

        return self::$connectionManager->get();
    }

    /**
     * Wraps $callback in a database transaction.
     *
     * If the callback throws, the transaction is rolled back and the
     * exception re-thrown. If it returns successfully, the transaction
     * is committed. The DBAL Connection is passed to the callback so
     * it can issue statements without a second static resolver call.
     *
     * @template T
     * @param \Closure(Connection): T $callback
     * @return T
     */
    public static function transaction(\Closure $callback): mixed
    {
        $connection = self::connection();

        return $connection->transactional($callback);
    }

    /**
     * Run a raw SELECT and return all rows as associative arrays.
     *
     * @param array<string, mixed> $params Named parameters, e.g. ['status' => 'active']
     * @return array<int, array<string, mixed>>
     */
    public static function select(string $sql, array $params = []): array
    {
        return self::connection()->fetchAllAssociative($sql, $params);
    }

    /**
     * Run a raw SELECT and return the first row, or null if no rows matched.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>|null
     */
    public static function selectOne(string $sql, array $params = []): ?array
    {
        $row = self::connection()->fetchAssociative($sql, $params);

        return $row === false ? null : $row;
    }

    /**
     * Run a raw INSERT / UPDATE / DELETE / DDL statement and return the
     * number of affected rows (0 for DDL statements).
     *
     * @param array<string, mixed> $params
     */
    public static function statement(string $sql, array $params = []): int
    {
        return (int) self::connection()->executeStatement($sql, $params);
    }

    /**
     * Run a raw SELECT and return a flat list of the first column's values —
     * useful for ID lists or single-column aggregates.
     *
     * @param array<string, mixed> $params
     * @return list<mixed>
     */
    public static function scalar(string $sql, array $params = []): mixed
    {
        return self::connection()->fetchOne($sql, $params);
    }
}
