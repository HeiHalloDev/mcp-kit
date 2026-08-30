<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staged files: a loading dock, not a warehouse. Bytes go up over HTTP
 * with the same token that talks MCP; a tool then consumes the handle and
 * copies the file into the app's real home. Rows expire and are pruned.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mcp_uploads')) {
            return;
        }

        Schema::create('mcp_uploads', function (Blueprint $table): void {
            $table->id();
            $table->string('handle', 40)->unique();

            // The person (or service client), NOT the token: tokens rotate
            // every 90 days and a re-mint must not orphan staged files.
            $table->string('owner_type');
            $table->string('owner_id');

            $table->string('name');
            $table->string('mime', 127)->nullable();
            $table->unsignedBigInteger('size');
            $table->string('checksum', 64);
            $table->string('disk', 64);
            $table->string('path');

            // What consumed it and where it went: [{tool, at, target?}].
            $table->jsonb('consumed')->nullable();

            $table->timestamp('expires_at')->index();
            $table->timestamps();

            $table->index(['owner_type', 'owner_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_uploads');
    }
};
