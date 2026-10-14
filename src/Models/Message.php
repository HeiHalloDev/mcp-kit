<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A message from the people who run the app to the people using its tools.
 *
 * @property int $id
 * @property string $body
 * @property ?list<string> $to_people names or e-mails
 * @property ?string $to_ability
 * @property bool $to_everyone
 * @property ?string $server
 * @property string $sent_by
 * @property ?Carbon $expires_at
 * @property Carbon $created_at
 */
class Message extends Model
{
    protected $table = 'mcp_messages';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'to_people' => 'array',
            'to_everyone' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    public function key(): string
    {
        return 'message:'.$this->id;
    }
}
