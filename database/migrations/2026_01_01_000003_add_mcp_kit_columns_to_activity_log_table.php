<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * source = the product a row belongs to; channel = the surface it was
 * written from (web, mcp, api, cli, chat, system); token_name = the
 * personal token used, when any. Each column and index guarded so an app
 * that already has some of them keeps them.
 */
return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('activitylog.database_connection');
    }

    public function up(): void
    {
        $table = (string) config('activitylog.table_name', 'activity_log');
        $schema = Schema::connection($this->getConnection());

        if (! $schema->hasTable($table)) {
            return;
        }

        $schema->table($table, function (Blueprint $blueprint) use ($schema, $table): void {
            if (! $schema->hasColumn($table, 'source')) {
                $blueprint->string('source', 100)->nullable()->index();
            }

            if (! $schema->hasColumn($table, 'channel')) {
                $blueprint->string('channel', 16)->nullable()->index();
            }

            if (! $schema->hasColumn($table, 'token_name')) {
                $blueprint->string('token_name')->nullable();
            }
        });

        $indexes = collect($schema->getIndexes($table))->pluck('name')->all();

        $schema->table($table, function (Blueprint $blueprint) use ($table, $indexes): void {
            if (! in_array("{$table}_causer_log_created_index", $indexes, true)) {
                $blueprint->index(['causer_type', 'causer_id', 'log_name', 'created_at'], "{$table}_causer_log_created_index");
            }

            if (! in_array("{$table}_log_created_index", $indexes, true)) {
                $blueprint->index(['log_name', 'created_at'], "{$table}_log_created_index");
            }
        });
    }

    public function down(): void
    {
        // The columns may predate the kit; leave them in place.
    }
};
