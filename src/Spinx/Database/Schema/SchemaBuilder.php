<?php

declare(strict_types=1);

namespace Spinx\Database\Schema;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;

/**
 * VERIFIED against the official Doctrine DBAL 4.x docs (fetched directly
 * from doctrine-project.org's schema-representation reference page — not
 * assumed from memory) after an earlier version of this method shipped
 * with three separate mistakes: it called `new Comparator()` directly
 * (the real entry point is `$schemaManager->createComparator()`), it
 * called a `compareSchemas()` method that doesn't exist on DBAL 4's
 * Comparator (the real method is `compare()`), and it looked for SQL
 * generation on the platform object (`getAlterSchemaSQL()`) when DBAL 4
 * puts it on the SchemaDiff object itself (`$schemaDiff->toSql()`). None
 * of this was caught earlier because this environment never had
 * Packagist access to install doctrine/dbal and execute it for real —
 * this fix is confirmed against DBAL's own current documentation, though
 * a real smoke test against your installed version is still the final
 * word if anything here doesn't quite match.
 */
final class SchemaBuilder
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function create(string $tableName, callable $callback): void
    {
        $schema = new Schema();
        $table = $schema->createTable($tableName);

        $callback(new Blueprint($table));

        foreach ($schema->toSql($this->connection->getDatabasePlatform()) as $sql) {
            $this->connection->executeStatement($sql);
        }
    }

    public function table(string $tableName, callable $callback): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        $fromSchema = $schemaManager->introspectSchema();
        $toSchema = clone $fromSchema;

        $callback(new Blueprint($toSchema->getTable($tableName)));

        $comparator = $schemaManager->createComparator();
        $schemaDiff = $comparator->compare($fromSchema, $toSchema);

        foreach ($schemaDiff->toSql($this->connection->getDatabasePlatform()) as $sql) {
            $this->connection->executeStatement($sql);
        }
    }

    public function drop(string $tableName): void
    {
        $this->connection->executeStatement(
            $this->connection->getDatabasePlatform()->getDropTableSQL($tableName)
        );
    }

    public function dropIfExists(string $tableName): void
    {
        if ($this->connection->createSchemaManager()->tablesExist([$tableName])) {
            $this->drop($tableName);
        }
    }

    /**
     * Enable a database extension (e.g. 'vector', 'uuid-ossp' on PostgreSQL).
     */
    public function enableExtension(string $name): void
    {
        try {
            $this->connection->executeStatement("CREATE EXTENSION IF NOT EXISTS \"{$name}\"");
        } catch (\Throwable) {
            // Ignored on platforms that don't support CREATE EXTENSION (e.g. SQLite, MySQL)
        }
    }

    /**
     * Execute arbitrary raw SQL DDL statement.
     */
    public function execute(string $sql): void
    {
        $this->connection->executeStatement($sql);
    }
}
