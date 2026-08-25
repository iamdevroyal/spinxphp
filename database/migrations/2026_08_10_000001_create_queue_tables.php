<?php

declare(strict_types=1);

use Spinx\Database\Migration;
use Spinx\Database\Schema\Blueprint;
use Spinx\Database\Schema\SchemaBuilder;

/**
 * Framework-internal migration (build spec — see Migrator::migrateFramework()
 * for why this lives in database/migrations/ instead of a specific
 * app/Modules/<Name>). Backs Spinx\Queue\QueueManager.
 */
return new class extends Migration {
    public function up(SchemaBuilder $schema): void
    {
        $schema->create('spinx_jobs', static function (Blueprint $table): void {
            $table->id();
            $table->text('payload');
            $table->integer('attempts');
            $table->timestamp('available_at', false);
            $table->timestamps();
        });

        $schema->create('spinx_failed_jobs', static function (Blueprint $table): void {
            $table->id();
            $table->text('payload');
            $table->text('exception');
            $table->timestamp('failed_at', false);
        });
    }

    public function down(SchemaBuilder $schema): void
    {
        $schema->dropIfExists('spinx_jobs');
        $schema->dropIfExists('spinx_failed_jobs');
    }
};
