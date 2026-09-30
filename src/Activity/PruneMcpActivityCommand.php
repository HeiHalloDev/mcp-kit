<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Activity;

use HeiHallo\McpKit\Contracts\TaskStore;
use HeiHallo\McpKit\Models\OAuthClient;
use HeiHallo\McpKit\Models\OAuthCode;
use HeiHallo\McpKit\Models\OAuthGrant;
use HeiHallo\McpKit\OAuth\AuthorizationServer;
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

    /**
     * Sign-ins: spent codes, grants whose refresh token ran out, revoked
     * grants past retention, and clients nobody has used in a while.
     */
    protected function pruneOAuth(int $days): void
    {
        if (! config('mcp-kit.oauth.enabled', false)) {
            return;
        }

        $codes = OAuthCode::query()->where('expires_at', '<', now()->subDay())->delete();

        $server = app(AuthorizationServer::class);
        $expired = 0;

        OAuthGrant::query()->active()->where('refresh_expires_at', '<', now())->each(function (OAuthGrant $grant) use ($server, &$expired): void {
            $server->revoke($grant, 'refresh_expired');
            $expired++;
        });

        $grants = OAuthGrant::query()->whereNotNull('revoked_at')->where('revoked_at', '<', now()->subDays($days))->delete();

        $idle = now()->subDays(max(1, (int) config('mcp-kit.oauth.client_idle_days', 90)));
        $clients = OAuthClient::query()
            ->whereDoesntHave('grants', fn ($query) => $query->whereNull('revoked_at'))
            ->where(fn ($query) => $query->where('last_used_at', '<', $idle)->orWhere(fn ($query) => $query->whereNull('last_used_at')->where('created_at', '<', $idle)))
            ->delete();

        $this->info("Pruned {$codes} sign-in code(s), {$grants} old grant(s) and {$clients} idle client(s); ended {$expired} expired grant(s).");
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
        $this->pruneOAuth($days);

        return self::SUCCESS;
    }
}
