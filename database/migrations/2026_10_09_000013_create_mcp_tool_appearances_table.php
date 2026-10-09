<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When each tool first appeared on each server. claude.ai keeps a
 * connector's tool list from when it connected, so the me resource tells a
 * person which tools arrived since then and that reconnecting brings them.
 * The tools there on the first record have no date: nothing to announce.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mcp_tool_appearances')) {
            return;
        }

        Schema::create('mcp_tool_appearances', function (Blueprint $table): void {
            $table->id();
            $table->string('server');
            $table->string('tool');
            $table->string('class');
            $table->timestamp('first_seen_at')->nullable();
            $table->unique(['server', 'tool']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_tool_appearances');
    }
};
