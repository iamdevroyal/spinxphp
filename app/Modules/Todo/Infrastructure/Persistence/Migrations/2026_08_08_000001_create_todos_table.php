<?php

declare(strict_types=1);

use Spinx\Database\Migration;
use Spinx\Database\Schema\Blueprint;
use Spinx\Database\Schema\SchemaBuilder;

/**
 * Reference module (step 10) proving Spinx works with zero frontend
 * JavaScript framework at all — see Infrastructure/Http/Views/index.spinx.html,
 * which uses only @foreach/@if/{{ }}, no @island anywhere. This is the
 * third reference implementation alongside the Vue (Health/Welcome) and
 * React (examples/react-frontend) examples.
 */
return new class extends Migration {
    public function up(SchemaBuilder $schema): void
    {
        $schema->create('todos', static function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->boolean('done');
            $table->timestamps();
        });
    }

    public function down(SchemaBuilder $schema): void
    {
        $schema->dropIfExists('todos');
    }
};
