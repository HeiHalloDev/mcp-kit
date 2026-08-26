<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How many times an assistant tried to name or close this frame and the
 * tool would not take it. Without it a frame that bounced seven times
 * reads exactly like one nobody touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('mcp_tasks', 'refusals')) {
            return;
        }

        Schema::table('mcp_tasks', function (Blueprint $table): void {
            $table->unsignedInteger('refusals')->default(0)->after('calls');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('mcp_tasks', 'refusals')) {
            return;
        }

        Schema::table('mcp_tasks', function (Blueprint $table): void {
            $table->dropColumn('refusals');
        });
    }
};
