<?php

declare(strict_types=1);

namespace Spinx\Database;

use Doctrine\DBAL\Connection;
use Spinx\Database\Schema\Blueprint;
use Spinx\Database\Schema\SchemaBuilder;

/**
 * Runs each module's migrations independently (build spec §5.2 —
 * `spinx module:migrate <Name>`), tracking what's already run per module
 * in a shared spinx_migrations table so re-running is always safe.
 *
 * Migration files are expected to be named with a sortable timestamp
 * prefix (e.g. 2026_08_08_000001_create_orders_table.php) so glob() +
 * sort() runs them in the order they were created, matching the
 * convention Laravel's migrations use.
 */
final class Migrator
{
    public function __construct(
        private readonly Connection $connection,
        private readonly SchemaBuilder $schema,
        private readonly string $projectRoot,
    ) {
    }

    public function ensureMigrationsTableExists(): void
    {
        if ($this->connection->createSchemaManager()->tablesExist(['spinx_migrations'])) {
            return;
        }

        $this->schema->create('spinx_migrations', static function (Blueprint $table): void {
            $table->id();
            $table->string('module');
            $table->string('migration');
            $table->integer('batch');
            $table->timestamp('ran_at', false);
        });
    }

    /** @return string[] Names of migration files that were run (empty if already up to date) */
    public function migrateModule(string $moduleName): array
    {
        $migrationsDir = $this->projectRoot . "/app/Modules/{$moduleName}/Infrastructure/Persistence/Migrations";

        return $this->runMigrationsIn($migrationsDir, $moduleName);
    }

    /**
     * Framework-internal migrations (queue tables, and anything else
     * that isn't a specific app module's concern) live in
     * database/migrations/ at the project root rather than under any
     * app/Modules/<Name> — tracked in the same spinx_migrations table
     * under the reserved module label "_framework", and run
     * automatically as part of every `spinx migrate`, no separate
     * command needed.
     *
     * @return string[]
     */
    public function migrateFramework(): array
    {
        return $this->runMigrationsIn($this->projectRoot . '/database/migrations', '_framework');
    }

    /** @return string[] */
    private function runMigrationsIn(string $migrationsDir, string $label): array
    {
        $this->ensureMigrationsTableExists();

        if (!is_dir($migrationsDir)) {
            return [];
        }

        $files = glob($migrationsDir . '/*.php') ?: [];
        sort($files);

        $alreadyRun = $this->connection->createQueryBuilder()
            ->select('migration')
            ->from('spinx_migrations')
            ->where('module = :module')
            ->setParameter('module', $label)
            ->executeQuery()
            ->fetchFirstColumn();

        $nextBatch = ((int) $this->connection->createQueryBuilder()
            ->select('MAX(batch)')
            ->from('spinx_migrations')
            ->executeQuery()
            ->fetchOne()) + 1;

        $executed = [];

        foreach ($files as $file) {
            $migrationName = basename($file, '.php');

            if (in_array($migrationName, $alreadyRun, true)) {
                continue;
            }

            $migration = require $file;

            if (!$migration instanceof Migration) {
                throw new \RuntimeException(sprintf(
                    'Migration file %s must return an instance of Spinx\Database\Migration.',
                    $file
                ));
            }

            $migration->up($this->schema);

            $this->connection->insert('spinx_migrations', [
                'module' => $label,
                'migration' => $migrationName,
                'batch' => $nextBatch,
                'ran_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);

            $executed[] = $migrationName;
        }

        return $executed;
    }

    /**
     * @param string[] $moduleNames
     * @return array<string, string[]> module name => migrations that were run ("_framework" included automatically)
     */
    public function migrateAll(array $moduleNames): array
    {
        $results = ['_framework' => $this->migrateFramework()];

        foreach ($moduleNames as $moduleName) {
            $results[$moduleName] = $this->migrateModule($moduleName);
        }

        return $results;
    }
}
