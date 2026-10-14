<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Messages;

use HeiHallo\McpKit\Contracts\AbilityCatalogue;
use HeiHallo\McpKit\Contracts\GapStore;
use HeiHallo\McpKit\Contracts\PresetResolver;
use HeiHallo\McpKit\Docs\ToolReference;
use HeiHallo\McpKit\Gaps\Gap;
use HeiHallo\McpKit\Mcp\Resources\MeResource;
use HeiHallo\McpKit\Models\Message;
use HeiHallo\McpKit\Models\MessageReceipt;
use HeiHallo\McpKit\Principal;
use HeiHallo\McpKit\Tools\AbilityProbe;
use HeiHallo\McpKit\Tools\ToolAppearances;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Throwable;

/**
 * Messages to the people using the tools, delivered where their assistant
 * reads: on top of the result of their next tool call, until the assistant
 * confirms with message_delivered that it passed the message on.
 *
 * Two kinds ride the same road. A message somebody sent (send_message, or
 * the usage page), and the answer to a gap the person reported. The second
 * used to sit in whoami and count as heard the moment it was read, which is
 * how a staff member learned in a meeting about tools built for him weeks
 * before.
 *
 * Recipients are matched by name or e-mail, the way gaps record reporters.
 */
class Messages
{
    public function enabled(): bool
    {
        return (bool) config('mcp-kit.messages.enabled', true);
    }

    /**
     * @param  list<string>  $people  names or e-mails
     */
    public function send(string $body, array $people, ?string $ability, bool $everyone, ?string $server, string $from, ?Carbon $expiresAt = null): Message
    {
        return Message::query()->create([
            'body' => trim($body),
            'to_people' => array_values(array_unique(array_filter(array_map('trim', $people)))) ?: null,
            'to_ability' => $ability ?: null,
            'to_everyone' => $everyone,
            'server' => $server ?: null,
            'sent_by' => $from,
            // A message to named people waits until it reaches them, however
            // long they stay away. A broadcast ("reconnect") goes stale.
            'expires_at' => $expiresAt ?? ($everyone || $ability ? now()->addDays((int) config('mcp-kit.messages.expires_days', 14)) : null),
        ]);
    }

    /**
     * What waits for this person on this server, oldest first. With $shownCap
     * only what has not yet been shown that many times, which is what rides
     * on tool results; whoami passes null and lists everything undelivered.
     *
     * @return list<array{key: string, kind: string, from: string, body: string, title: ?string, sent_at: ?string}>
     */
    public function pendingFor(Principal $principal, ?string $server, ?int $shownCap = null): array
    {
        if (! $this->enabled() || ! $principal->isPerson()) {
            return [];
        }

        $pending = [];

        try {
            foreach ($this->messagesFor($principal, $server) as $message) {
                $pending[] = [
                    'key' => $message->key(),
                    'kind' => 'message',
                    'from' => $message->sent_by,
                    'body' => $message->body,
                    'title' => null,
                    'sent_at' => $message->created_at?->toIso8601String(),
                ];
            }

            if ($server !== null && ($reconnect = $this->reconnectFor($principal, $server)) !== null) {
                $pending[] = $reconnect;
            }

            foreach ($this->gapAnswersFor($principal) as $gap) {
                $pending[] = [
                    'key' => $this->gapKey($gap),
                    'kind' => 'gap',
                    'from' => (string) ($gap->resolvedBy ?? ''),
                    'body' => (string) $gap->resolution,
                    'title' => $gap->title,
                    'status' => $gap->status,
                    'sent_at' => $gap->resolvedAt?->format(DATE_ATOM),
                ];
            }

            $receipts = MessageReceipt::query()
                ->where('recipient', $principal->name)
                ->whereIn('message_key', array_column($pending, 'key'))
                ->get()
                ->keyBy('message_key');
        } catch (Throwable $e) {
            // An app that has not migrated yet still answers its tool calls
            // and its whoami.
            report($e);

            return [];
        }

        return array_values(array_filter($pending, function (array $item) use ($receipts, $shownCap): bool {
            $receipt = $receipts->get($item['key']);

            return $receipt?->delivered_at === null
                && ($shownCap === null || (int) ($receipt?->shown ?? 0) < $shownCap);
        }));
    }

    /**
     * The people behind the names and addresses given, or why not.
     *
     * @param  list<string>  $to
     * @return list<array{name: string, address: string}>|string
     */
    public function resolvePeople(mixed $model, array $to): array|string
    {

        if (! $model instanceof Model) {
            return 'People cannot be looked up in this app.';
        }

        $found = [];
        $problems = [];

        foreach ($to as $who) {
            $matches = $model->newQuery()
                ->whereRaw('lower(email) = ?', [mb_strtolower($who)])
                ->orWhereRaw('lower(name) = ?', [mb_strtolower($who)])
                ->limit(3)
                ->get();

            if ($matches->isEmpty()) {
                $problems[] = "nobody here is called or has the address \"{$who}\"";

                continue;
            }

            if ($matches->count() > 1) {
                $problems[] = "\"{$who}\" matches more than one person; use their e-mail";

                continue;
            }

            $person = $matches->first();
            $found[] = ['name' => (string) $person->getAttribute('name'), 'address' => (string) ($person->getAttribute('email') ?: $person->getAttribute('name'))];
        }

        return $problems === [] ? $found : 'Nothing was sent: '.implode('; ', $problems).'.';
    }

    /**
     * Count one more showing of each.
     *
     * @param  list<string>  $keys
     */
    public function markShown(Principal $principal, array $keys): void
    {
        foreach ($keys as $key) {
            $receipt = MessageReceipt::query()->firstOrNew(['message_key' => $key, 'recipient' => $principal->name]);
            $receipt->shown = (int) $receipt->shown + 1;
            $receipt->last_shown_at = now();
            $receipt->save();
        }
    }

    /**
     * The assistant passed these on. A gap answer is marked heard too, so
     * whoami stops listing it.
     *
     * @param  list<string>  $keys
     * @return list<string> the keys that were waiting for this person
     */
    public function deliver(Principal $principal, array $keys, ?string $server = null): array
    {
        // The server matters: a reconnect reminder belongs to one connection,
        // and without it the reminder could never be confirmed.
        $waiting = array_column($this->pendingFor($principal, $server), null, 'key');
        $done = [];

        foreach ($keys as $key) {
            if (! isset($waiting[$key])) {
                continue;
            }

            MessageReceipt::query()->updateOrCreate(
                ['message_key' => $key, 'recipient' => $principal->name],
                ['delivered_at' => now()],
            );

            if (str_starts_with($key, 'gap:') && ($gap = app(GapStore::class)->find(explode(':', $key)[1])) !== null) {
                app(GapStore::class)->markHeard($gap, $principal->name);
            }

            $done[] = $key;
        }

        return $done;
    }

    /**
     * The block that rides on a tool result.
     *
     * @param  list<array<string, mixed>>  $items
     */
    public function block(Principal $principal, array $items): string
    {
        $first = $principal->firstName();
        $lines = [
            sprintf('MESSAGE FOR %s from the people who run this app. Tell %s now, in your next reply and before you go on with the task: keep the substance, say it in your own words and the language you are speaking with them. Then call `message_delivered` with the ids below. Until you do, this comes back on their next tool calls.', $principal->name, $first),
            '',
        ];

        foreach ($items as $item) {
            $lines[] = match ($item['kind']) {
                'gap' => sprintf('[%s] The answer to something %s reported, "%s" (%s by %s): %s', $item['key'], $first, $item['title'], $item['status'] ?? 'settled', $item['from'], $item['body']),
                'reconnect' => sprintf('[%s] %s', $item['key'], $item['body']),
                default => sprintf('[%s] From %s: %s', $item['key'], $item['from'], $item['body']),
            };
        }

        $lines[] = '';
        $lines[] = 'If it names tools you do not have: a connection made by signing in (Claude or ChatGPT, under Settings and Connectors or Apps) keeps the tool list it saw when connecting, so tell them to disconnect it and connect again, then start a new chat. With a token in Claude Code or Codex, a new session is enough.';

        return implode("\n", $lines);
    }

    /**
     * @return list<Message>
     */
    protected function messagesFor(Principal $principal, ?string $server): array
    {
        $who = array_filter([mb_strtolower($principal->name), mb_strtolower((string) $principal->email)]);
        $probe = app(AbilityProbe::class);

        return Message::query()
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->when($server !== null, fn ($q) => $q->where(fn ($inner) => $inner->whereNull('server')->orWhere('server', $server)))
            ->orderBy('id')
            ->get()
            ->filter(fn (Message $message): bool => $message->to_everyone
                || ($message->to_ability !== null && $probe->grants($principal, $message->to_ability))
                || array_intersect($who, array_map('mb_strtolower', (array) $message->to_people)) !== [])
            ->values()
            ->all();
    }

    /**
     * @return list<Gap>
     */
    protected function gapAnswersFor(Principal $principal): array
    {
        if (! config('mcp-kit.gaps.enabled', true)) {
            return [];
        }

        // Older answers are history, not news: the first release with
        // messages would otherwise deliver every answer nobody heard.
        $since = now()->subDays((int) config('mcp-kit.messages.gap_answers_days', 30));

        return array_values(array_filter(
            app(GapStore::class)->settledFor($principal->name),
            fn (Gap $gap): bool => filled($gap->resolution) && ($gap->resolvedAt === null || $gap->resolvedAt >= $since),
        ));
    }

    /**
     * Tools this person may use that arrived after they connected: their
     * client still shows the list it saw then. Gone as soon as they
     * reconnect, because the connection is newer than the tools.
     *
     * A pasted token is the other case. Its client fetches the tools again
     * every session, but the token keeps the abilities it was minted with,
     * so a tool behind an ability that did not exist yet never reaches it,
     * while the instructions describe it.
     *
     * @return array<string, mixed>|null
     */
    protected function reconnectFor(Principal $principal, string $server): ?array
    {
        if (! config('mcp-kit.messages.reconnect', true)) {
            return null;
        }

        if ($principal->viaSignIn()) {
            $rows = MeResource::newTools($principal, app(AbilityCatalogue::class)->expand($principal->abilities()), $server);

            if ($rows === []) {
                return null;
            }

            $tools = $this->toolNames($rows);

            return $this->reminder('reconnect', $server, $tools, sprintf(
                'New tools have come to this connection since %s connected on %s: %s. Their assistant does not have them yet. To get them: in Claude or ChatGPT, open Settings, then Connectors (in ChatGPT: Apps), disconnect this connection and connect it again, then start a new chat.',
                $principal->firstName(),
                $principal->connectedAt()?->format('j M Y') ?? 'an earlier day',
                $this->toolList($tools),
            ));
        }

        $tools = $this->beyondToken($principal, $server);

        if ($tools === []) {
            return null;
        }

        $page = config('mcp-kit.ui.memory_url') ?: (Route::has('mcp-kit.tokens') ? route('mcp-kit.tokens') : null);

        return $this->reminder('token', $server, $tools, sprintf(
            'New tools have come that %s may use, but the token this connection uses (made %s) cannot reach them: %s. It keeps the permissions it was made with. To get them: make a new token%s, put it in place of the old one in Claude Code or Codex, delete the old one, and start a new session.',
            $principal->firstName(),
            $principal->connectedAt()?->format('j M Y') ?? 'earlier',
            $this->toolList($tools),
            $page ? ' at '.$page : ' where the old one was made',
        ));
    }

    /**
     * Tools that arrived after the token was made, that its person may use,
     * and that the token cannot.
     *
     * @return list<string>
     */
    protected function beyondToken(Principal $principal, string $server): array
    {
        $since = $principal->connectedAt();

        if ($since === null) {
            return [];
        }

        $catalogue = app(AbilityCatalogue::class);
        $held = $catalogue->expand($principal->abilities());
        $grantable = app(PresetResolver::class)->grantableFor($principal->tokenable);
        $reference = app(ToolReference::class);
        $tools = [];

        foreach (app(ToolAppearances::class)->since($server, $since) as $row) {
            $needs = array_values(array_filter(
                class_exists($row['class']) ? $reference->abilitiesFor($row['class']) : [],
                fn (string $ability): bool => ! str_contains($ability, '*'),
            ));

            if ($needs !== [] && array_intersect($needs, $held) === [] && array_intersect($needs, $grantable) !== []) {
                $tools[] = $row['tool'];
            }
        }

        return $this->toolNames(array_map(fn (string $tool): array => ['tool' => $tool], $tools));
    }

    /**
     * @param  list<array{tool: string}>  $rows
     * @return list<string>
     */
    protected function toolNames(array $rows): array
    {
        $tools = array_values(array_unique(array_column($rows, 'tool')));
        sort($tools);

        return $tools;
    }

    /**
     * @param  list<string>  $tools
     */
    protected function toolList(array $tools): string
    {
        return implode(', ', array_map(fn (string $tool): string => '`'.$tool.'`', $tools));
    }

    /**
     * @param  list<string>  $tools
     * @return array<string, mixed>
     */
    protected function reminder(string $kind, string $server, array $tools, string $body): array
    {
        return [
            'key' => $kind.':'.$server.':'.substr(md5(implode(',', $tools)), 0, 10),
            'kind' => 'reconnect',
            'from' => 'this app',
            'body' => $body,
            'title' => null,
            'sent_at' => null,
        ];
    }

    protected function gapKey(Gap $gap): string
    {
        return 'gap:'.$gap->id.':'.($gap->resolvedAt?->getTimestamp() ?? 0);
    }
}
