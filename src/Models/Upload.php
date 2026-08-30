<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A staged file waiting to be consumed by a tool.
 *
 * @property int $id
 * @property string $handle
 * @property string $owner_type
 * @property string $owner_id
 * @property string $name
 * @property ?string $mime
 * @property int $size
 * @property string $checksum
 * @property string $disk
 * @property string $path
 * @property ?array $consumed
 * @property Carbon $expires_at
 */
class Upload extends Model
{
    protected $table = 'mcp_uploads';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'consumed' => 'array',
            'expires_at' => 'datetime',
            'size' => 'integer',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
