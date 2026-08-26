<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A task frame row.
 *
 * @property int $id
 * @property string $token_id
 * @property string $user_id
 * @property string $name
 * @property string $purpose
 * @property ?string $server
 * @property string $outcome
 * @property ?string $result
 * @property ?string $effort
 * @property int $calls
 * @property ?Carbon $closed_at
 */
class Task extends Model
{
    protected $guarded = [];

    protected $attributes = [
        'outcome' => 'open',
        'calls' => 0,
    ];

    public function getTable(): string
    {
        return $this->table ?? (string) config('mcp-kit.learning.table', 'mcp_tasks');
    }

    protected function casts(): array
    {
        return [
            'calls' => 'integer',
            'closed_at' => 'datetime',
        ];
    }
}
