<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Mcp\Tools;

use HeiHallo\McpKit\Contracts\GapStore;
use HeiHallo\McpKit\Events\GapReported;
use HeiHallo\McpKit\Events\GapStatusChanged;
use HeiHallo\McpKit\Gaps\ComposesGaps;
use HeiHallo\McpKit\Gaps\Gap;
use HeiHallo\McpKit\Memory\SecretDetector;
use HeiHallo\McpKit\Principal;
use HeiHallo\McpKit\Servers\ServerRegistry;
use HeiHallo\McpKit\Tools\StaffTool;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

class ReportGapTool extends StaffTool
{
    use ComposesGaps;

    protected string $name = 'report_gap';

    protected string $description = 'File something the person needed that this app cannot do. Not for refusals: if a tool said an ability or permission was missing, that is a permissions question with a named fix, not a gap. A repeat of something already reported joins it instead of duplicating, so just file it. Previews without confirm=true. Privileged staff may set status.';

    /**
     * @var array<string, mixed>
     */
    protected array $inputSchema = [
        'type' => 'object',
        'properties' => [
            'title' => ['type' => 'string', 'description' => 'The gap in a few words, as the person would say it: "cannot move a signup between studies".'],
            'need' => ['type' => 'string', 'description' => 'What they were trying to get done, in their words.'],
            'missing' => ['type' => 'string', 'description' => 'What was not there, and what they expected instead.'],
            'server' => ['type' => 'string', 'description' => 'The server key it belongs to, when it is clearly one of them.'],
            'tool' => ['type' => 'string', 'description' => 'The tool they reached for, if there was one.'],
            'blocking' => ['type' => 'boolean', 'description' => 'True when it stopped the work, false when they found a way round.'],
            'note' => ['type' => 'string', 'description' => 'What this person said about it, kept alongside their name.'],
            'status' => ['type' => 'string', 'enum' => ['open', 'planned', 'done', 'declined'], 'description' => 'Privileged only: move an existing gap.'],
            'resolution' => ['type' => 'string', 'description' => 'Privileged only: what was decided or built, when setting status.'],
            'gap' => ['type' => 'string', 'description' => 'Privileged only: the id of the gap to move.'],
            'confirm' => ['type' => 'boolean', 'description' => 'Preview without it; file with true.'],
        ],
    ];

    public function handle(Request $request): Response
    {
        $principal = $this->requirePerson($request);

        if ($principal instanceof Response) {
            return $principal;
        }

        if (! config('mcp-kit.gaps.enabled', true)) {
            return Response::error('Gap reports are turned off in this app.');
        }

        if ($request->get('status') !== null || $request->get('gap') !== null) {
            return $this->move($request, $principal);
        }

        return $this->report($request, $principal);
    }

    protected function report(Request $request, Principal $principal): Response
    {
        $input = $request->all();

        if (SecretDetector::looksSecret($input)) {
            return Response::error('That looks like a password or token. A gap report describes what was missing, never a credential.');
        }

        try {
            $title = $this->required($request, 'title');
            $need = $this->required($request, 'need');
            $missing = $this->required($request, 'missing');
            $key = Gap::key($title);
            $server = $this->server($request);
        } catch (InvalidArgumentException $e) {
            return Response::error($e->getMessage());
        }

        $store = app(GapStore::class);
        $existing = $store->openMatching($key);
        $note = trim((string) $request->get('note', ''));
        $blocking = filter_var($request->get('blocking', false), FILTER_VALIDATE_BOOL);

        if ($existing !== null && $existing->reportedBy($principal->name)) {
            return Response::error(sprintf(
                "You already reported '%s' — it is open with %d %s behind it. Nothing more to add unless something changed.",
                $existing->title,
                $existing->reports,
                $existing->reports === 1 ? 'report' : 'reports',
            ));
        }

        $gap = $this->composeGap(
            $principal,
            title: $title,
            need: $need,
            missing: $missing,
            server: $server,
            tool: $this->text($request, 'tool'),
            blocking: $blocking,
            note: $note,
        );

        return $this->previewOrExecute(
            $request,
            array_filter([
                'gap' => $gap->title,
                'change' => $existing === null ? 'new report' : sprintf('adds you to an open gap, making it %d reports', $gap->reports),
                'needed' => $gap->need,
                'missing' => $gap->missing,
                'server' => $gap->server,
                'tool' => $gap->tool,
                'blocking' => $gap->blocking ? 'yes — it stopped the work' : 'no — there was a way round',
                'goes_to' => 'the people who build this app',
            ], static fn (mixed $value): bool => $value !== null),
            function () use ($store, $gap, $principal, $existing): array {
                $saved = $store->put($gap);

                event(new GapReported($saved, $principal, $existing === null));

                return ['gap' => $saved->toArray()];
            },
            $existing === null ? 'Report a gap' : 'Add to an open gap',
        );
    }

    protected function move(Request $request, Principal $principal): Response
    {
        if (! $principal->privileged) {
            return Response::error('Moving a gap is for privileged staff. Report it and someone will pick it up.');
        }

        $id = $request->get('gap');
        $status = (string) $request->get('status', '');

        if ($id === null) {
            return Response::error('Which gap? Pass its id — {scheme}://gaps lists them.');
        }

        if (! in_array($status, Gap::STATUSES, true)) {
            return Response::error('Status is one of: '.implode(', ', Gap::STATUSES).'.');
        }

        $store = app(GapStore::class);
        $gap = $store->find($id);

        if ($gap === null) {
            return Response::error("No gap with id {$id}.");
        }

        if ($gap->status === $status) {
            return Response::error("'{$gap->title}' is already {$status}.");
        }

        $resolution = trim((string) $request->get('resolution', ''));

        if ($resolution === '' && in_array($status, [Gap::DONE, Gap::DECLINED], true)) {
            return Response::error('Say what was decided: a gap closed without a reason tells the person who reported it nothing.');
        }

        $closing = in_array($status, [Gap::DONE, Gap::DECLINED], true);

        $moved = new Gap(
            key: $gap->key,
            title: $gap->title,
            need: $gap->need,
            missing: $gap->missing,
            server: $gap->server,
            tool: $gap->tool,
            blocking: $gap->blocking,
            status: $status,
            reporters: $gap->reporters,
            reports: $gap->reports,
            resolution: $resolution === '' ? $gap->resolution : $resolution,
            resolvedBy: $closing ? $principal->name : $gap->resolvedBy,
            resolvedAt: $closing ? now() : $gap->resolvedAt,
            reportedAt: $gap->reportedAt,
            id: $gap->id,
        );

        return $this->previewOrExecute(
            $request,
            array_filter([
                'gap' => $gap->title,
                'from' => $gap->status,
                'to' => $status,
                'resolution' => $moved->resolution,
                'reported_by' => implode(', ', array_column($gap->reporters, 'name')),
            ], static fn (mixed $value): bool => $value !== null && $value !== ''),
            function () use ($store, $moved, $gap, $principal): array {
                $saved = $store->put($moved);

                event(new GapStatusChanged($saved, $gap->status, $principal));

                return ['gap' => $saved->toArray()];
            },
            'Move a gap',
        );
    }

    /**
     * @return array{name: string, at: string, note: string}
     */
    protected function required(Request $request, string $key): string
    {
        $value = trim((string) $request->get($key, ''));

        if ($value === '') {
            throw new InvalidArgumentException(match ($key) {
                'title' => 'A gap needs a title: the thing that is missing, in a few words.',
                'need' => 'Say what the person was trying to get done — without it nobody can judge the gap.',
                default => 'Say what was missing, and what they expected instead.',
            });
        }

        return $value;
    }

    protected function text(Request $request, string $key): ?string
    {
        $value = trim((string) $request->get($key, ''));

        return $value === '' ? null : $value;
    }

    protected function server(Request $request): ?string
    {
        $server = $this->text($request, 'server');

        if ($server === null) {
            return null;
        }

        $known = app(ServerRegistry::class)->keys();

        if (! in_array($server, $known, true)) {
            throw new InvalidArgumentException(sprintf(
                "There is no server called '%s'. This app has: %s.",
                $server,
                $known === [] ? 'none' : implode(', ', $known),
            ));
        }

        return $server;
    }
}
