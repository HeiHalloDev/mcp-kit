<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Mcp\Tools;

use HeiHallo\McpKit\Messages\Messages;
use HeiHallo\McpKit\Tools\StaffTool;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

/**
 * The assistant's word that it told the person. Until then a message keeps
 * coming back on their tool results.
 */
class MessageDeliveredTool extends StaffTool
{
    protected string $name = 'message_delivered';

    protected string $description = 'Confirm you passed on a MESSAGE FOR the person you are helping. Call it right after telling them, with the ids shown in square brackets, e.g. ["message:12", "gap:7:1760000000"]. Until you do, the message comes back on their tool results. Only for messages you actually passed on.';

    /** @var array<string, mixed> */
    protected array $inputSchema = [
        'type' => 'object',
        'properties' => [
            'ids' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'The ids in square brackets in the message.'],
        ],
        'required' => ['ids'],
    ];

    public function handle(Request $request, Messages $messages): Response
    {
        $principal = $this->requirePerson($request);

        if ($principal instanceof Response) {
            return $principal;
        }

        $ids = array_values(array_filter(array_map(fn ($v) => trim((string) $v, " []\t"), (array) $request->get('ids', []))));
        $done = $messages->deliver($principal, $ids);
        $left = count($messages->pendingFor($principal, null));

        return Response::json(array_filter([
            'delivered' => $done,
            'not_waiting' => array_values(array_diff($ids, $done)) ?: null,
            'still_waiting' => $left ?: null,
        ], fn ($value) => $value !== null));
    }
}
