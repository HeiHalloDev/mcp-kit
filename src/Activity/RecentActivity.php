<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Activity;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Activity;
use Throwable;

/**
 * What a person did through the tools lately: call count, top tools, last
 * confirmed changes. Read from the `mcp` rows, cached briefly.
 */
class RecentActivity
{
    public function __construct(protected Cache $cache) {}

    /**
     * @return array{days: int, calls: int, top_tools: list<array{tool: string, calls: int}>, last_changes: list<array{action: string, at: string, tool: ?string}>}
     */
    public function for(Authenticatable $user): array
    {
        $days = (int) config('mcp-kit.activity.recent_days', 14);
        $seconds = (int) config('mcp-kit.activity.recent_cache_seconds', 300);
        $key = sprintf('mcp-kit:recent:%s:%s', $user::class, $user->getAuthIdentifier());

        return $this->cache->remember($key, $seconds, fn (): array => $this->compute($user, $days));
    }

    public function forget(Authenticatable $user): void
    {
        $this->cache->forget(sprintf('mcp-kit:recent:%s:%s', $user::class, $user->getAuthIdentifier()));
    }

    /**
     * @return array{days: int, calls: int, top_tools: list<array{tool: string, calls: int}>, last_changes: list<array{action: string, at: string, tool: ?string}>}
     */
    protected function compute(Authenticatable $user, int $days): array
    {
        $empty = ['days' => $days, 'calls' => 0, 'top_tools' => [], 'last_changes' => []];

        if (! $user instanceof Model) {
            return $empty;
        }

        try {
            $model = (string) config('activitylog.activity_model', Activity::class);
            $since = now()->subDays($days);

            $rows = $model::query()
                ->where('log_name', config('mcp-kit.activity.log_name', 'mcp'))
                ->where('causer_type', $user->getMorphClass())
                ->where('causer_id', $user->getKey())
                ->where('created_at', '>=', $since)
                ->whereIn('event', ['read', 'previewed', 'executed'])
                ->orderByDesc('created_at')
                ->limit(2000)
                ->get(['event', 'description', 'properties', 'created_at']);

            $counts = [];
            $changes = [];

            foreach ($rows as $row) {
                $tool = (string) (collect($row->properties ?? [])->get('tool') ?? '');

                if ($tool !== '') {
                    $counts[$tool] = ($counts[$tool] ?? 0) + 1;
                }

                if ($row->event === 'executed' && count($changes) < 3) {
                    $changes[] = [
                        'action' => (string) $row->description,
                        'at' => $row->created_at?->toDateTimeString() ?? '',
                        'tool' => $tool !== '' ? $tool : null,
                    ];
                }
            }

            arsort($counts);

            return [
                'days' => $days,
                'calls' => $rows->count(),
                'top_tools' => array_map(
                    static fn (string $tool, int $calls): array => ['tool' => $tool, 'calls' => $calls],
                    array_keys(array_slice($counts, 0, 5, true)),
                    array_values(array_slice($counts, 0, 5, true)),
                ),
                'last_changes' => $changes,
            ];
        } catch (Throwable $e) {
            report($e);

            return $empty;
        }
    }
}
