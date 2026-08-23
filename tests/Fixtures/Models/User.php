<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property ?string $role
 * @property ?array $permissions
 * @property ?Carbon $blocked_at
 */
class User extends Authenticatable
{
    use HasApiTokens;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'blocked_at' => 'datetime',
        ];
    }

    public function isBlocked(): bool
    {
        return $this->blocked_at !== null;
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
