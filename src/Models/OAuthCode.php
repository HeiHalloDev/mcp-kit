<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The one-time code between "the person said yes" and "the client holds a
 * token". Lives a minute, is used once, and is stored as a hash.
 *
 * @property int $id
 * @property string $code_hash
 * @property int $client_id
 * @property string $user_id
 * @property list<string> $abilities
 * @property string $redirect_uri
 * @property string $code_challenge
 * @property ?string $resource
 * @property Carbon $expires_at
 * @property ?Carbon $used_at
 */
class OAuthCode extends Model
{
    protected $table = 'mcp_oauth_codes';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'abilities' => 'array',
            'declined' => 'array',
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<OAuthClient, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(OAuthClient::class, 'client_id');
    }
}
