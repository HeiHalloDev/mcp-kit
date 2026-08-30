<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Activity;

use HeiHallo\McpKit\Contracts\TaskStore;
use HeiHallo\McpKit\Uploads\Uploads;
use Illuminate\Console\Command;
use Spatie\Activitylog\Models\Activity;

/**
 * Deletes `mcp` rows older than the retention window, in chunks, and
 * the task frames that described them.
 */
class PruneMcpActivityCommand extends Command
{
    protected $signature = 'mcp-kit:prune {--days= : Override mcp-kit.activity.retain_days} {--chunk=1000}';

    protected $description = 'Delete old MCP tool-call rows from the activity log';

    /**
     * Task frames age out with the calls they describe: a purpose whose
     * activity rows are gone tells nobody anything.
     */
    protected function pruneTasks(): void
    {
        if (! config('mcp-kit.learning.enabled', false)) {
            return;
        }

        $days = (int) ($this->option('days')
            ?: config('mcp-kit.learning.retain_days')
            ?? config('mcp-kit.activity.retain_days', 90));

        if ($days <= 0) {
            return;
        }

        $deleted = app(TaskStore::class)->prune($days);

        $this->info("Pruned {$deleted} task frame(s) older than {$days} days.");
    }

    /**
     * Staged files are a loading dock: whatever nobody consumed goes with
     * its row when the clock runs out.
     */
    protected function pruneUploads(): void
    {
        if (! config('mcp-kit.uploads.enabled', false)) {
            return;
        }

        $pruned = app(Uploads::class)->prune();

        $this->info("Pruned {$pruned} expired staged upload(s).");
    }

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('mcp-kit.activity.retain_days', 90));

        if ($days <= 0) {
            $this->info('Retention is off (retain_days <= 0); nothing pruned.');

            return self::SUCCESS;
        }

        $model = (string) config('activitylog.activity_model', Activity::class);
        $chunk = max(100, (int) $this->option('chunk'));
        $deleted = 0;

        do {
            $batch = $model::query()
                ->where('log_name', config('mcp-kit.activity.log_name', 'mcp'))
                ->where('created_at', '<', now()->subDays($days))
                ->limit($chunk)
                ->delete();

            $deleted += $batch;
        } while ($batch === $chunk);

        $this->info("Pruned {$deleted} MCP activity row(s) older than {$days} days.");

        $this->pruneTasks();
        $this->pruneUploads();

        return self::SUCCESS;
    }
}
