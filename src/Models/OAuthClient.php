<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An MCP client that registered itself (Claude, ChatGPT, Codex, Claude
 * Code). Public: it holds no secret and proves each sign-in with PKCE.
 *
 * @property int $id
 * @property string $client_id
 * @property string $name
 * @property list<string> $redirect_uris
 * @property ?string $registered_ip
 * @property ?Carbon $last_used_at
 */
class OAuthClient extends Model
{
    protected $table = 'mcp_oauth_clients';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'redirect_uris' => 'array',
            'last_used_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<OAuthGrant, $this>
     */
    public function grants(): HasMany
    {
        return $this->hasMany(OAuthGrant::class, 'client_id');
    }
}
