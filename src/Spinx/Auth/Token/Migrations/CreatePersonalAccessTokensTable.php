<?php

declare(strict_types=1);

namespace Spinx\Auth\Token\Migrations;

use Spinx\Database\Schema\SchemaBuilder;
use Spinx\Database\Schema\Blueprint;

/**
 * Creates the personal_access_tokens table for Spinx Token Authentication.
 *
 * Run automatically via: php spinx migrate
 * Or register in your module's migration list.
 */
final class CreatePersonalAccessTokensTable
{
    public function up(SchemaBuilder $schema): void
    {
        $schema->create('personal_access_tokens', function (Blueprint $table): void {
            $table->id();
            $table->string('tokenable_type');                    // e.g. App\Modules\Auth\Domain\Entities\User
            $table->unsignedBigInteger('tokenable_id');          // FK to user/model ID
            $table->string('name');                              // Device/app name (e.g. "iPhone 15 Pro")
            $table->string('token', 64)->unique();               // SHA-256 hash of plaintext
            $table->text('abilities')->nullable();               // JSON-encoded ability array, e.g. ["read","write"]
            $table->timestamp('last_used_at')->nullable();       // Updated on each valid request
            $table->timestamp('expires_at')->nullable();         // Optional hard expiry
            $table->timestamps();

            // Composite index for fast tokenable lookups (list user's tokens)
            $table->index(['tokenable_type', 'tokenable_id'], 'pat_tokenable_idx');
        });
    }

    public function down(SchemaBuilder $schema): void
    {
        $schema->dropIfExists('personal_access_tokens');
    }
}
