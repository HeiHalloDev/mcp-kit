<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One message and one person: how often it rode on their tool results, and
 * when their assistant confirmed it passed it on.
 *
 * @property string $message_key message:{id} or gap:{id}:{settled at}
 * @property string $recipient the person's name, as gaps record reporters
 * @property int $shown
 * @property ?Carbon $last_shown_at
 * @property ?Carbon $delivered_at
 */
class MessageReceipt extends Model
{
    protected $table = 'mcp_message_receipts';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'last_shown_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }
}
