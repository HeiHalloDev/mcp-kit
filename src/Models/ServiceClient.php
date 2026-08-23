<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Models;

use HeiHallo\McpKit\Contracts\ServiceClient as ServiceClientContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

/**
 * A tokenable with no person behind it: another system calling in. Reads
 * by default; writes only where mcp-kit.catalogue.service_client_writes
 * allows.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property ?string $description
 * @property bool $is_active
 * @property ?Carbon $last_used_at
 */
class ServiceClient extends Authenticatable implements ServiceClientContract
{
    use HasApiTokens;

    protected $table = 'mcp_service_clients';

    protected $guarded = [];

    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_used_at' => 'datetime',
        ];
    }

    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    public function touchLastUsed(): void
    {
        $this->forceFill(['last_used_at' => now()])->saveQuietly();
    }

    public function displayName(): string
    {
        return (string) $this->name;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
