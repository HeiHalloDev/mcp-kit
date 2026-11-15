<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = $this->table();

        if (! Schema::hasTable($table) || Schema::hasColumn($table, 'follow_ups')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint): void {
            // What was said after the gap was filed, oldest first: who, when
            // and what. A retest or a question back used to be written over
            // the resolution, where the next one replaced it and the list
            // never showed it while the gap was open.
            $blueprint->jsonb('follow_ups')->nullable();
        });
    }

    public function down(): void
    {
        $table = $this->table();

        if (Schema::hasTable($table) && Schema::hasColumn($table, 'follow_ups')) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn('follow_ups'));
        }
    }

    private function table(): string
    {
        return (string) config('mcp-kit.gaps.table', 'mcp_gap_reports');
    }
};
