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

        if (! Schema::hasTable($table) || Schema::hasColumn($table, 'effort')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint): void {
            // Nullable: frames closed before this shipped have no judgement,
            // and saying so is better than guessing one for them.
            $blueprint->string('effort')->nullable()->index();
        });
    }

    public function down(): void
    {
        $table = $this->table();

        if (Schema::hasTable($table) && Schema::hasColumn($table, 'effort')) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn('effort'));
        }
    }

    private function table(): string
    {
        return (string) config('mcp-kit.learning.table', 'mcp_tasks');
    }
};
