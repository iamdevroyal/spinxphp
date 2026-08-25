<?php

declare(strict_types=1);

use Spinx\Database\Migration;
use Spinx\Database\Schema\Blueprint;
use Spinx\Database\Schema\SchemaBuilder;

/**
 * Reference migration proving the schema/migrator pipeline from build
 * step 5 works end to end — logs every /health hit via HealthCheckLog.
 */
return new class extends Migration {
    public function up(SchemaBuilder $schema): void
    {
        $schema->create('health_checks', static function (Blueprint $table): void {
            $table->id();
            $table->string('status');
            $table->timestamps();
        });
    }

    public function down(SchemaBuilder $schema): void
    {
        $schema->dropIfExists('health_checks');
    }
};
