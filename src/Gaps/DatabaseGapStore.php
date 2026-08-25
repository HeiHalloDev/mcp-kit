<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Gaps;

use HeiHallo\McpKit\Contracts\GapStore;
use HeiHallo\McpKit\Models\GapReport;

/**
 * Gaps in the mcp_gap_reports table. Routing them onwards is the app's
 * job: listen for GapReported and open whatever your team actually reads.
 */
class DatabaseGapStore implements GapStore
{
    public function openMatching(string $key): ?Gap
    {
        $row = $this->model()->newQuery()
            ->where('key', $key)
            ->where('status', Gap::OPEN)
            ->first();

        return $row === null ? null : $this->toGap($row);
    }

    public function find(int|string $id): ?Gap
    {
        $row = $this->model()->newQuery()->find($id);

        return $row === null ? null : $this->toGap($row);
    }

    public function list(array $statuses = [Gap::OPEN], ?string $server = null, int $limit = 50): array
    {
        $rows = $this->model()->newQuery()
            ->when($statuses !== [], fn ($query) => $query->whereIn('status', $statuses))
            ->when($server !== null, fn ($query) => $query->where(function ($inner) use ($server): void {
                $inner->whereNull('server')->orWhere('server', $server);
            }))
            ->orderByDesc('blocking')
            ->orderByDesc('reports')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return $rows->map(fn (GapReport $row): Gap => $this->toGap($row))->all();
    }

    public function put(Gap $gap): Gap
    {
        $attributes = [
            'key' => $gap->key,
            'title' => $gap->title,
            'need' => $gap->need,
            'missing' => $gap->missing,
            'server' => $gap->server,
            'tool' => $gap->tool,
            'blocking' => $gap->blocking,
            'status' => $gap->status,
            'reporters' => $gap->reporters,
            'reports' => $gap->reports,
            'resolution' => $gap->resolution,
            'resolved_by' => $gap->resolvedBy,
            'resolved_at' => $gap->resolvedAt,
        ];

        $row = $gap->id === null
            ? $this->model()->newQuery()->create($attributes)
            : tap($this->model()->newQuery()->findOrFail($gap->id))->update($attributes);

        return $this->toGap($row);
    }

    protected function toGap(GapReport $row): Gap
    {
        return new Gap(
            key: (string) $row->key,
            title: (string) $row->title,
            need: (string) $row->need,
            missing: (string) $row->missing,
            server: $row->server === null ? null : (string) $row->server,
            tool: $row->tool === null ? null : (string) $row->tool,
            blocking: (bool) $row->blocking,
            status: (string) $row->status,
            reporters: $this->reporters($row),
            reports: (int) $row->reports,
            resolution: $row->resolution === null ? null : (string) $row->resolution,
            resolvedBy: $row->resolved_by === null ? null : (string) $row->resolved_by,
            resolvedAt: $row->resolved_at,
            reportedAt: $row->created_at,
            id: $row->id,
        );
    }

    /**
     * @return list<array{name: string, at: string, note: string}>
     */
    protected function reporters(GapReport $row): array
    {
        $reporters = [];

        foreach ($row->reporters ?? [] as $reporter) {
            if (! is_array($reporter) || ! isset($reporter['name'])) {
                continue;
            }

            $reporters[] = [
                'name' => (string) $reporter['name'],
                'at' => (string) ($reporter['at'] ?? ''),
                'note' => (string) ($reporter['note'] ?? ''),
            ];
        }

        return $reporters;
    }

    protected function model(): GapReport
    {
        $class = (string) config('mcp-kit.gaps.model', GapReport::class);

        return new $class;
    }
}
