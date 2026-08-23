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
        $column = (string) config('mcp-kit.memory.column', 'assistant_memory');

        if (! Schema::hasTable($table) || Schema::hasColumn($table, $column)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column): void {
            // JSONB on Postgres, JSON elsewhere — Laravel picks per driver.
            $blueprint->jsonb($column)->nullable();
        });
    }

    public function down(): void
    {
        $table = $this->table();
        $column = (string) config('mcp-kit.memory.column', 'assistant_memory');

        if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn($column));
        }
    }

    private function table(): string
    {
        $configured = config('mcp-kit.memory.table');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        $model = config('auth.providers.users.model');

        return is_string($model) && class_exists($model) ? (new $model)->getTable() : 'users';
    }
};
