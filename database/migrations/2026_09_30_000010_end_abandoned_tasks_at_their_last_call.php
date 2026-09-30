<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Until v1.18.1 an abandoned frame was closed at the moment somebody came
 * back and found it stale, which can be days after the work stopped. Move
 * each one's end to its last call, read from the activity log — the row's
 * own updated_at was overwritten by the same update. A frame with no
 * logged calls keeps what it has.
 */
return new class extends Migration
{
    public function up(): void
    {
        $tasks = (string) config('mcp-kit.learning.table', 'mcp_tasks');
        $activity = (string) config('activitylog.table_name', 'activity_log');
        $log = DB::connection(config('activitylog.database_connection'));

        if (! Schema::hasTable($tasks) || ! $log->getSchemaBuilder()->hasTable($activity)) {
            return;
        }

        DB::table($tasks)->where('outcome', 'unknown')->orderBy('id')->each(function (object $task) use ($tasks, $activity, $log): void {
            $last = $log->table($activity)
                ->where('log_name', (string) config('mcp-kit.activity.log_name', 'mcp'))
                ->where('properties->task', (string) $task->id)
                ->max('created_at');

            if ($last !== null) {
                DB::table($tasks)->where('id', $task->id)->update(['closed_at' => $last, 'updated_at' => $last]);
            }
        });
    }

    public function down(): void
    {
        // The old end was the time somebody happened to come back; nothing to restore.
    }
};
