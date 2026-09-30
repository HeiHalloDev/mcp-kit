<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * What a person said yes to: this client may act as them, with these
 * abilities, on this server. Outlives every token issued under it — the
 * access token turns over hourly, the grant stays until it is revoked or
 * its refresh token goes unused for too long.
 *
 * @property int $id
 * @property int $client_id
 * @property string $user_id
 * @property list<string> $abilities
 * @property ?string $resource
 * @property ?int $access_token_id
 * @property ?string $refresh_hash
 * @property ?string $previous_refresh_hash
 * @property ?string $grace_payload
 * @property ?Carbon $rotated_at
 * @property ?Carbon $refresh_expires_at
 * @property ?Carbon $last_used_at
 * @property ?Carbon $revoked_at
 * @property ?string $revoked_reason
 * @property Carbon $created_at
 * @property-read OAuthClient $client
 */
class OAuthGrant extends Model
{
    protected $table = 'mcp_oauth_grants';

    protected $guarded = [];

    protected $hidden = ['refresh_hash', 'previous_refresh_hash', 'grace_payload'];

    protected function casts(): array
    {
        return [
            'abilities' => 'array',
            'declined' => 'array',
            'rotated_at' => 'datetime',
            'refresh_expires_at' => 'datetime',
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<OAuthClient, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(OAuthClient::class, 'client_id');
    }

    /**
     * @param  Builder<OAuthGrant>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('revoked_at');
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }

    /**
     * The key task frames and hints are kept under: stable for as long as
     * the grant lives, whatever token the call came in on.
     */
    public function frameKey(): string
    {
        return 'oauth:'.$this->getKey();
    }
}
