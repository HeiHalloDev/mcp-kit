<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A reported gap. Apps that already have a tracker point
 * mcp-kit.gaps.store at their own GapStore instead of swapping this model.
 *
 * @property int $id
 * @property string $key
 * @property string $title
 * @property string $need
 * @property string $missing
 * @property ?string $server
 * @property ?string $tool
 * @property bool $blocking
 * @property string $status
 * @property ?array<int, array<string, string>> $reporters
 * @property int $reports
 * @property ?string $resolution
 * @property ?string $resolved_by
 * @property ?Carbon $resolved_at
 */
class GapReport extends Model
{
    protected $guarded = [];

    protected $attributes = [
        'status' => 'open',
        'reports' => 1,
        'blocking' => false,
    ];

    public function getTable(): string
    {
        return $this->table ?? (string) config('mcp-kit.gaps.table', 'mcp_gap_reports');
    }

    protected function casts(): array
    {
        return [
            'reporters' => 'array',
            'blocking' => 'boolean',
            'reports' => 'integer',
            'resolved_at' => 'datetime',
        ];
    }
}
