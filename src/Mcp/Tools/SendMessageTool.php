<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Mcp\Tools;

use HeiHallo\McpKit\Contracts\AbilityCatalogue;
use HeiHallo\McpKit\Messages\Messages;
use HeiHallo\McpKit\Servers\ServerRegistry;
use HeiHallo\McpKit\Tools\StaffTool;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

/**
 * A message to colleagues, delivered by their own assistant: it rides on
 * top of their next tool results here until the assistant confirms it told
 * them. For "the course is ready for you", "I fixed the price you asked
 * about", "you can grade hand-ins with the tools now".
 *
 * Anybody can message people by name or e-mail. Messaging everyone, or
 * everyone holding an ability, is for Owner and Dev.
 */
class SendMessageTool extends StaffTool
{
    protected string $name = 'send_message';

    protected string $description = 'Send a message to one or more colleagues who use these tools, like messaging a teammate: "the course is ready for you to review", "done with the import you asked for". It reaches them through their own assistant, on top of the result of their next tool call in this app, until the assistant confirms it told them. Address people by name or e-mail in `to`. Owner and Dev may also send to everyone holding an ability (`ability`) or to everyone (`everyone`). Write it the way you would write to them, in their language. list_messages shows whether it arrived. Preview first.';

    /** @var array<string, mixed> */
    protected array $inputSchema = [
        'type' => 'object',
        'properties' => [
            'to' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Names or e-mails of the people to tell.'],
            'body' => ['type' => 'string', 'description' => 'The message, as they should hear it. Plain text.'],
            'ability' => ['type' => 'string', 'description' => 'Owner and Dev: everyone whose connection holds this ability, e.g. a new tool\'s ability.'],
            'everyone' => ['type' => 'boolean', 'description' => 'Owner and Dev: everyone who uses the tools here.'],
            'server' => ['type' => 'string', 'description' => 'Only on this server of the app. Leave out for all of them.'],
            'expires_days' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 90, 'description' => 'Stop delivering after this many days. A message to named people waits until it reaches them unless you set this; one to everyone or an ability stops after 14 days.'],
            'confirm' => ['type' => 'boolean', 'description' => 'Preview without it; send with true.'],
        ],
        'required' => ['body'],
    ];

    public function handle(Request $request, Messages $messages): Response
    {
        $principal = $this->requirePerson($request);

        if ($principal instanceof Response) {
            return $principal;
        }

        if (! $messages->enabled()) {
            return Response::error('Messages are turned off in this app.');
        }

        $body = trim((string) $request->get('body', ''));
        $ability = trim((string) $request->get('ability', '')) ?: null;
        $everyone = filter_var($request->get('everyone'), FILTER_VALIDATE_BOOL);
        $server = trim((string) $request->get('server', '')) ?: null;
        $to = array_values(array_filter(array_map(fn ($v) => trim((string) $v), (array) $request->get('to', []))));

        if ($body === '') {
            return Response::error('body is empty. Write the message as they should hear it.');
        }

        if (mb_strlen($body) > 2000) {
            return Response::error('Keep a message under 2,000 characters. It rides on top of their tool results, so say the point and where to find the rest.');
        }

        if (($ability !== null || $everyone) && ! $principal->privileged) {
            return Response::error('Messaging everyone, or everyone holding an ability, is for Owner and Dev. Name the people in `to`.');
        }

        if ($to === [] && $ability === null && ! $everyone) {
            return Response::error('Say who it is for: names or e-mails in `to`.');
        }

        if ($ability !== null && app(AbilityCatalogue::class)->description(app(AbilityCatalogue::class)->canonical($ability)) === null) {
            return Response::error("No ability {$ability} in this app.");
        }

        if ($server !== null && app(ServerRegistry::class)->get($server) === null) {
            return Response::error("No server {$server} in this app.");
        }

        $people = $messages->resolvePeople($principal->tokenable, $to);

        if (is_string($people)) {
            return Response::error($people);
        }

        $broadcast = $everyone || $ability !== null;
        $days = $request->get('expires_days') !== null
            ? min(max($request->integer('expires_days'), 1), 90)
            : ($broadcast ? (int) config('mcp-kit.messages.expires_days', 14) : null);
        $expiresAt = $days === null ? null : now()->addDays($days);

        return $this->previewOrExecute($request, array_filter([
            'to' => array_column($people, 'name') ?: null,
            'ability' => $ability,
            'everyone' => $everyone ?: null,
            'server' => $server,
            'from' => $principal->name,
            'message' => $body,
            'delivered' => 'by their own assistant, on their next tool calls in this app, until it confirms it told them',
            'expires' => $expiresAt?->toDateString() ?? 'never: it waits until it reaches them',
        ], fn ($value) => $value !== null), function () use ($messages, $body, $people, $ability, $everyone, $server, $principal, $expiresAt): array {
            $message = $messages->send($body, array_column($people, 'name'), $ability, $everyone, $server, $principal->name, $expiresAt);

            return ['sent' => $message->key(), 'to' => array_column($people, 'name'), 'check' => 'list_messages shows when it reaches them.'];
        }, 'send a message');
    }
}
