<?php

declare(strict_types=1);

namespace Spinx\Database\Schema;

use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;

/**
 * Fluent column-definition DSL used inside Migration::up()/down()
 * callbacks. Wraps a Doctrine\DBAL\Schema\Table rather than generating
 * raw SQL directly, so column definitions stay portable across whichever
 * database driver is configured in spinx.json (SQLite by default,
 * MySQL/Postgres via config — build spec §11).
 */
final class Blueprint
{
    public function __construct(
        private readonly Table $table,
    ) {
    }

    public function id(string $name = 'id'): static
    {
        $this->table->addColumn($name, Types::BIGINT, ['autoincrement' => true, 'unsigned' => true]);
        $this->table->setPrimaryKey([$name]);

        return $this;
    }

    public function string(string $name, int $length = 255, bool $nullable = false): static
    {
        $this->table->addColumn($name, Types::STRING, ['length' => $length, 'notnull' => !$nullable]);

        return $this;
    }

    public function text(string $name, bool $nullable = false): static
    {
        $this->table->addColumn($name, Types::TEXT, ['notnull' => !$nullable]);

        return $this;
    }

    public function integer(string $name, bool $nullable = false, bool $unsigned = false): static
    {
        $this->table->addColumn($name, Types::INTEGER, ['notnull' => !$nullable, 'unsigned' => $unsigned]);

        return $this;
    }

    public function bigInteger(string $name, bool $nullable = false, bool $unsigned = false): static
    {
        $this->table->addColumn($name, Types::BIGINT, ['notnull' => !$nullable, 'unsigned' => $unsigned]);

        return $this;
    }

    public function boolean(string $name, bool $nullable = false): static
    {
        $this->table->addColumn($name, Types::BOOLEAN, ['notnull' => !$nullable]);

        return $this;
    }

    public function decimal(string $name, int $precision = 10, int $scale = 2, bool $nullable = false): static
    {
        $this->table->addColumn($name, Types::DECIMAL, ['precision' => $precision, 'scale' => $scale, 'notnull' => !$nullable]);

        return $this;
    }

    public function json(string $name, bool $nullable = false): static
    {
        $this->table->addColumn($name, Types::JSON, ['notnull' => !$nullable]);

        return $this;
    }

    public function timestamp(string $name, bool $nullable = true): static
    {
        $this->table->addColumn($name, Types::DATETIME_IMMUTABLE, ['notnull' => !$nullable]);

        return $this;
    }

    /** Adds created_at + updated_at, both nullable timestamps managed automatically by Model::save(). */
    public function timestamps(): static
    {
        $this->timestamp('created_at');
        $this->timestamp('updated_at');

        return $this;
    }

    /** Adds deleted_at for models with protected bool $softDeletes = true. */
    public function softDeletes(): static
    {
        return $this->timestamp('deleted_at');
    }

    /** Adds an unsigned bigint foreign-key column (does not add the constraint itself — see foreign()). */
    public function foreignId(string $name): static
    {
        $this->table->addColumn($name, Types::BIGINT, ['unsigned' => true, 'notnull' => true]);

        return $this;
    }

    public function index(string ...$columns): static
    {
        $this->table->addIndex($columns);

        return $this;
    }

    public function unique(string ...$columns): static
    {
        $this->table->addUniqueIndex($columns);

        return $this;
    }

    public function foreign(string $column, string $referencesTable, string $referencesColumn = 'id'): static
    {
        $this->table->addForeignKeyConstraint($referencesTable, [$column], [$referencesColumn]);

        return $this;
    }
}
