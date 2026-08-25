<?php

declare(strict_types=1);

use Spinx\Database\Migration;
use Spinx\Database\Schema\Blueprint;
use Spinx\Database\Schema\SchemaBuilder;

return new class extends Migration {
    public function up(SchemaBuilder $schema): void
    {
        $schema->create('users', static function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
        });
    }

    public function down(SchemaBuilder $schema): void
    {
        $schema->dropIfExists('users');
    }
};
