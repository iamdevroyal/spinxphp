<?php

declare(strict_types=1);

namespace Spinx\Database;

use Spinx\Database\Schema\SchemaBuilder;

/**
 * Every migration file under a module's
 * Infrastructure/Persistence/Migrations/ directory returns an instance of
 * an anonymous class extending this — see Migrator for how these are
 * discovered and run, module-scoped, per build spec §5.2.
 */
abstract class Migration
{
    abstract public function up(SchemaBuilder $schema): void;

    abstract public function down(SchemaBuilder $schema): void;
}
