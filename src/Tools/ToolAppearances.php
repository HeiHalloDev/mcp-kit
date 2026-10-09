<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Tools;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * When each tool first appeared on a server. Recorded as servers boot,
 * written only when the set of tool classes changes, so a request costs a
 * cache read. The first record of a server carries no dates: those tools
 * were there before anybody could have been told, so none of them is news.
 */
class ToolAppearances
{
    public const TABLE = 'mcp_tool_appearances';

    /**
     * @param  list<class-string>  $classes
     */
    public function record(string $server, array $classes): void
    {
        $classes = array_values(array_unique(array_filter($classes, 'is_string')));
        sort($classes);
        $key = 'mcp-kit:tool-appearances:'.$server.':'.md5(implode('|', $classes));

        if (Cache::has($key)) {
            return;
        }

        try {
            if (! Schema::hasTable(self::TABLE)) {
                return;
            }

            $known = DB::table(self::TABLE)->where('server', $server)->pluck('class')->all();
            $baseline = $known === [];
            $rows = [];

            foreach (array_diff($classes, $known) as $class) {
                $rows[] = [
                    'server' => $server,
                    'tool' => (string) app($class)->name(),
                    'class' => $class,
                    'first_seen_at' => $baseline ? null : now(),
                ];
            }

            if ($rows !== []) {
                DB::table(self::TABLE)->insertOrIgnore($rows);
            }

            Cache::forever($key, true);
        } catch (Throwable) {
            // Never in the way of a call: the notice is a courtesy.
        }
    }

    /**
     * Tools that appeared on the server after a moment, newest first.
     *
     * @return list<array{tool: string, class: string, first_seen_at: string}>
     */
    public function since(string $server, \DateTimeInterface $moment): array
    {
        try {
            return DB::table(self::TABLE)
                ->where('server', $server)
                ->whereNotNull('first_seen_at')
                ->where('first_seen_at', '>', $moment)
                ->orderByDesc('first_seen_at')
                ->get(['tool', 'class', 'first_seen_at'])
                ->map(fn (object $row): array => ['tool' => (string) $row->tool, 'class' => (string) $row->class, 'first_seen_at' => (string) $row->first_seen_at])
                ->all();
        } catch (Throwable) {
            return [];
        }
    }
}
