<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A saved playbook row. Apps that keep playbooks somewhere else point
 * mcp-kit.playbooks.store at their own PlaybookStore instead of swapping
 * this model.
 *
 * @property int $id
 * @property string $user_id
 * @property string $name
 * @property string $title
 * @property string $description
 * @property string $body
 * @property ?array<int, array<string, mixed>> $arguments
 * @property ?array<int, string> $servers
 * @property ?array<int, string> $abilities
 * @property bool $shared
 * @property int $uses
 * @property ?Carbon $last_used_at
 */
class Playbook extends Model
{
    protected $guarded = [];

    protected $attributes = [
        'shared' => false,
        'uses' => 0,
    ];

    public function getTable(): string
    {
        return $this->table ?? (string) config('mcp-kit.playbooks.table', 'mcp_playbooks');
    }

    protected function casts(): array
    {
        return [
            'arguments' => 'array',
            'servers' => 'array',
            'abilities' => 'array',
            'shared' => 'boolean',
            'uses' => 'integer',
            'last_used_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeVisibleTo(Builder $query, int|string $userId): void
    {
        $query->where(function (Builder $inner) use ($userId): void {
            $inner->where('user_id', (string) $userId)->orWhere('shared', true);
        });
    }
}
