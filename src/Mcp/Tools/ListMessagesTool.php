<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Mcp\Tools;

use HeiHallo\McpKit\Messages\Messages;
use HeiHallo\McpKit\Models\Message;
use HeiHallo\McpKit\Models\MessageReceipt;
use HeiHallo\McpKit\Tools\StaffTool;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * Whether a message arrived: per person, how many times it rode on their
 * tool results and when their assistant confirmed it told them.
 */
#[IsReadOnly]
class ListMessagesTool extends StaffTool
{
    protected string $name = 'list_messages';

    protected string $description = 'The messages you sent with send_message, newest first, and for each person whether it reached them: delivered (their assistant confirmed it told them, and when), shown (on their tool results, not confirmed yet) or waiting (they have not used the tools since). Owner and Dev see everybody\'s messages with all=true.';

    /** @var array<string, mixed> */
    protected array $inputSchema = [
        'type' => 'object',
        'properties' => [
            'all' => ['type' => 'boolean', 'description' => 'Owner and Dev: every message, not only yours.'],
            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20],
        ],
    ];

    public function handle(Request $request, Messages $messages): Response
    {
        $principal = $this->requirePerson($request);

        if ($principal instanceof Response) {
            return $principal;
        }

        $all = filter_var($request->get('all'), FILTER_VALIDATE_BOOL) && $principal->privileged;
        $rows = Message::query()
            ->when(! $all, fn ($q) => $q->where('sent_by', $principal->name))
            ->latest('id')
            ->limit(min(max($request->integer('limit', 20), 1), 50))
            ->get();

        $receipts = MessageReceipt::query()->whereIn('message_key', $rows->map->key()->all())->get()->groupBy('message_key');

        return Response::json(['messages' => $rows->map(function (Message $message) use ($receipts): array {
            $seen = $receipts->get($message->key(), collect())->keyBy(fn (MessageReceipt $r) => mb_strtolower($r->recipient));
            $people = collect((array) $message->to_people)->map(fn (string $who) => mb_strtolower($who));

            return array_filter([
                'id' => $message->key(),
                'from' => $message->sent_by,
                'sent_at' => $message->created_at?->toIso8601String(),
                'expires_at' => $message->expires_at?->toIso8601String(),
                'to' => $message->to_people,
                'ability' => $message->to_ability,
                'everyone' => $message->to_everyone ?: null,
                'message' => $message->body,
                'reached' => $seen->map(fn (MessageReceipt $r) => array_filter([
                    'person' => $r->recipient,
                    'status' => $r->delivered_at ? 'delivered' : 'shown',
                    'delivered_at' => $r->delivered_at?->toIso8601String(),
                    'shown' => $r->shown,
                ]))->values()->all(),
                'not_yet_seen_by' => $people->isEmpty() ? null : (array_values(array_diff($people->all(), $seen->keys()->all())) ?: null),
            ], fn ($value) => $value !== null);
        })->all()]);
    }
}
