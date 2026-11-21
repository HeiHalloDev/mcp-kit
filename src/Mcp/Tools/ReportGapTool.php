<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Mcp\Tools;

use HeiHallo\McpKit\Contracts\GapStore;
use HeiHallo\McpKit\Contracts\PrincipalResolver;
use HeiHallo\McpKit\Events\GapReported;
use HeiHallo\McpKit\Events\GapStatusChanged;
use HeiHallo\McpKit\Gaps\ComposesGaps;
use HeiHallo\McpKit\Gaps\FollowUps;
use HeiHallo\McpKit\Gaps\Gap;
use HeiHallo\McpKit\Memory\SecretDetector;
use HeiHallo\McpKit\Messages\Recipients;
use HeiHallo\McpKit\Neighbours\Neighbours;
use HeiHallo\McpKit\Principal;
use HeiHallo\McpKit\Servers\ServerRegistry;
use HeiHallo\McpKit\Tools\StaffTool;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

class ReportGapTool extends StaffTool
{
    use ComposesGaps;

    protected string $name = 'report_gap';

    protected string $description = 'File something the person needed that this app cannot do. Not for refusals: if a tool said an ability or permission was missing, that is a permissions question with a named fix, not a gap — and if it belongs to a sister connection, the preview says so before anything is filed. A repeat of something already reported joins it instead of duplicating, so just file it. To add to one already filed (a retest, a new example, an answer to a question) pass `gap` with its id and `note`: it is kept as a dated follow-up beside the report and reaches the other side. `gap` alone shows that gap with its follow-ups. Previews without confirm=true. Privileged staff may also settle one in the same call (status + resolution), correct a resolution, and merge a duplicate into the gap it repeats (merge_into).';

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
            'note' => ['type' => 'string', 'description' => 'What this person said about it, kept alongside their name. With `gap`: the follow-up to add to that gap.'],
            'for' => ['type' => 'string', 'description' => 'Privileged only: file it as this person\'s report (name, first name or e-mail of someone with an account here), when they asked for it some other way, by e-mail or in a meeting. The answer then goes to them, not to you.'],
            'status' => ['type' => 'string', 'enum' => ['open', 'planned', 'done', 'declined'], 'description' => 'Privileged only: move an existing gap. Not for a note on one: that is `gap` with `note`.'],
            'resolution' => ['type' => 'string', 'description' => 'Privileged only: what was decided or built, when setting status.'],
            'gap' => ['type' => 'string', 'description' => 'The id of a gap already filed, from the reply when it was filed. With `note` it adds a follow-up; alone it shows the gap and its follow-ups. For the people who reported it and privileged staff. Privileged only: with `status` it moves the gap. Leave it out to file a new one.'],
            'merge_into' => ['type' => 'string', 'description' => 'Privileged only: the id of the gap this one repeats. Its reporters move across and this one closes pointing at it.'],
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

        if ($request->get('merge_into') !== null) {
            return $this->merge($request, $principal);
        }

        // A status with an id moves that gap; a status without one settles
        // the gap this call is filing, which is how something already
        // answered elsewhere gets filed and closed in one go.
        if ($request->get('gap') !== null) {
            // An id without a status is not a move: it is somebody adding
            // to what was filed, or wanting to read it.
            return trim((string) $request->get('status', '')) === ''
                ? $this->followUp($request, $principal)
                : $this->move($request, $principal);
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
            $title = Gap::title($this->required($request, 'title'));
            $need = $this->required($request, 'need');
            $missing = $this->required($request, 'missing');
            $key = Gap::key($title);
            $server = $this->server($request);
        } catch (InvalidArgumentException $e) {
            return Response::error($e->getMessage());
        }

        $reporter = $this->reporterFor($request, $principal);

        if ($reporter instanceof Response) {
            return $reporter;
        }

        $store = app(GapStore::class);
        $existing = $store->openMatching($key);
        $note = trim((string) $request->get('note', ''));
        $blocking = filter_var($request->get('blocking', false), FILTER_VALIDATE_BOOL);

        if ($existing !== null && $existing->reportedBy($reporter->name)) {
            // Reporting it again with something to say is a follow-up: the
            // person has news about their own gap, and refusing it sent
            // that news into the resolution field or nowhere.
            if ($note !== '') {
                return $this->addFollowUp($request, $reporter, $existing, $note);
            }

            return Response::error(sprintf(
                ($reporter === $principal ? 'You' : $reporter->name)." already reported '%s' — it is open as #%s with %d %s behind it. If something changed, pass `note` with what changed (or `gap` %s and `note`) and it is added as a follow-up.",
                $existing->title,
                $existing->id,
                $existing->reports,
                $existing->reports === 1 ? 'report' : 'reports',
                $existing->id,
            ));
        }

        $gap = $this->composeGap(
            $reporter,
            title: $title,
            need: $need,
            missing: $missing,
            server: $server,
            tool: $this->text($request, 'tool'),
            blocking: $blocking,
            note: $note,
        );

        // Somebody who cannot do something here often can do it next door.
        // Said in the preview, before anything is filed, so the answer
        // arrives while they are still asking — and the confirm still
        // files it, because a wrong guess must never swallow a report.
        $elsewhere = app(Neighbours::class)->match($title, $need, $missing);

        // Privileged staff settle as they file: the gap that turns out to
        // live in another connection is filed and answered in one call.
        $settle = $this->settlement($request, $principal);

        if ($settle instanceof Response) {
            return $settle;
        }

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
                'may_live_in' => $elsewhere === null ? null : app(Neighbours::class)->hint($elsewhere),
                'files_as' => $settle === null ? null : $settle['status'].': '.$settle['resolution'],
                'reported_by' => $reporter === $principal ? null : $reporter->name.' (the answer goes to them)',
                'goes_to' => 'the people who build this app',
            ], static fn (mixed $value): bool => $value !== null),
            function () use ($store, $gap, $principal, $existing, $settle): array {
                $saved = $store->put($gap);

                event(new GapReported($saved, $principal, $existing === null));

                if ($settle !== null) {
                    $before = $saved->status;
                    $saved = $store->put($saved->settled($settle['status'], $settle['resolution'], $principal->name));

                    event(new GapStatusChanged($saved, $before, $principal));
                }

                return ['gap' => $saved->toArray()];
            },
            $existing === null ? 'Report a gap' : 'Add to an open gap',
        );
    }

    /**
     * The status and resolution to apply as the gap is filed, when a
     * privileged caller passed them. Null when they did not.
     *
     * @return array{status: string, resolution: string}|Response|null
     */
    protected function settlement(Request $request, Principal $principal): array|Response|null
    {
        $status = trim((string) $request->get('status', ''));

        if ($status === '' || $status === Gap::OPEN) {
            return null;
        }

        if (! $principal->privileged) {
            return Response::error('Settling a gap is for privileged staff. File it without a status and someone will pick it up.');
        }

        if (! in_array($status, Gap::STATUSES, true)) {
            return Response::error('Status is one of: '.implode(', ', Gap::STATUSES).'.');
        }

        $resolution = trim((string) $request->get('resolution', ''));

        if ($resolution === '') {
            return Response::error('Say what was decided. It is what the people who reported this will read.');
        }

        return ['status' => $status, 'resolution' => $resolution];
    }

    /**
     * Whose report this is: the caller's, or with `for` (privileged), the
     * named person's. A staff member's assistant e-mailed its findings when
     * it could not reach the developer; filed by the developer, the gaps
     * counted as his and the answers came back to him instead.
     */
    protected function reporterFor(Request $request, Principal $principal): Principal|Response
    {
        $for = trim((string) $request->get('for', ''));

        if ($for === '') {
            return $principal;
        }

        if (! $principal->privileged) {
            return Response::error('Filing a gap for someone else is for privileged staff. File it as your own, or ask them to file it.');
        }

        $matches = app(Recipients::class)->matchAnyone($principal->tokenable instanceof Model ? $principal->tokenable : throw new \LogicException('No user model.'), $for);

        if ($matches->count() !== 1) {
            return Response::error($matches->isEmpty()
                ? "Nobody here is called or has the address \"{$for}\"."
                : "\"{$for}\" matches ".$matches->count().' people ('.$matches->map(fn (Model $person): string => (string) $person->getAttribute('name'))->implode(', ').'); use their full name or e-mail.');
        }

        $person = $matches->first();
        $resolved = $person instanceof Authenticatable ? app(PrincipalResolver::class)->resolve($person) : null;

        return $resolved ?? Response::error("\"{$for}\" cannot report gaps here.");
    }

    /**
     * Two rows describing one thing: the reporters move across, the count
     * adds up, and the duplicate closes pointing at the survivor. Matching
     * is on the title, so people who phrased it differently each got their
     * own row — merging is the only way to make the weight real.
     */
    protected function merge(Request $request, Principal $principal): Response
    {
        if (! $principal->privileged) {
            return Response::error('Merging gaps is for privileged staff.');
        }

        $store = app(GapStore::class);
        $duplicate = $request->get('gap') === null ? null : $store->find((string) $request->get('gap'));
        $survivor = $store->find((string) $request->get('merge_into'));

        if ($request->get('gap') === null) {
            return Response::error('Which gap is the duplicate? Pass `gap` (the one to close) and `merge_into` (the one it repeats).');
        }

        if ($duplicate === null || $survivor === null) {
            return Response::error('No gap with id '.($duplicate === null ? $request->get('gap') : $request->get('merge_into')).'.');
        }

        if ((string) $duplicate->id === (string) $survivor->id) {
            return Response::error('A gap cannot be merged into itself.');
        }

        $reporters = $survivor->reporters;
        $names = array_column($reporters, 'name');
        $moved = [];

        foreach ($duplicate->reporters as $reporter) {
            if (! in_array($reporter['name'], $names, true)) {
                unset($reporter['heard']);
                $reporters[] = $reporter;
                $moved[] = $reporter['name'];
            }
        }

        $followUps = [...$survivor->followUps, ...$duplicate->followUps];
        usort($followUps, static fn (array $a, array $b): int => strcmp($a['at'], $b['at']));

        $merged = new Gap(
            key: $survivor->key,
            title: $survivor->title,
            need: $survivor->need,
            missing: $survivor->missing,
            server: $survivor->server,
            tool: $survivor->tool,
            blocking: $survivor->blocking || $duplicate->blocking,
            status: $survivor->status,
            reporters: $reporters,
            reports: count($reporters) ?: $survivor->reports,
            resolution: $survivor->resolution,
            resolvedBy: $survivor->resolvedBy,
            resolvedAt: $survivor->resolvedAt,
            reportedAt: $survivor->reportedAt,
            id: $survivor->id,
            followUps: $followUps,
        );

        $closed = $duplicate->settled(
            Gap::DECLINED,
            'Same thing as #'.$survivor->id.' ('.$survivor->title.'), merged into it. Follow that one.',
            $principal->name,
        );

        return $this->previewOrExecute(
            $request,
            [
                'merging' => '#'.$duplicate->id.' '.$duplicate->title,
                'into' => '#'.$survivor->id.' '.$survivor->title,
                'reporters_moving' => $moved === [] ? 'none — the same people reported both' : implode(', ', $moved),
                'reports_after' => $merged->reports,
                'duplicate_becomes' => 'declined, pointing at #'.$survivor->id,
            ],
            function () use ($store, $merged, $closed, $duplicate, $principal): array {
                $saved = $store->put($merged);
                $store->put($closed);

                event(new GapStatusChanged($closed, $duplicate->status, $principal));

                return ['gap' => $saved->toArray(), 'merged' => '#'.$duplicate->id.' is now declined and points at #'.$saved->id];
            },
            'Merge a duplicate gap',
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

        $resolution = trim((string) $request->get('resolution', ''));

        // An open gap has no decision to reword. Words written on it this
        // way are a follow-up, and are kept as one: each used to replace
        // the last, in a field the list does not show for an open gap.
        if ($status === Gap::OPEN && $gap->status === Gap::OPEN && $resolution !== '') {
            return $this->addFollowUp($request, $principal, $gap, $resolution);
        }

        // Re-stating a status is how a resolution gets corrected: the words
        // are what the reporter reads, and the first draft is not always
        // the one you want them to read.
        if ($gap->status === $status && $resolution === '') {
            return Response::error("'{$gap->title}' is already {$status}. Pass a resolution with it to reword what the reporters will read.");
        }

        if ($resolution === '' && in_array($status, [Gap::DONE, Gap::DECLINED], true)) {
            return Response::error('Say what was decided: a gap closed without a reason tells the person who reported it nothing.');
        }

        $words = $resolution === '' ? (string) $gap->resolution : $resolution;

        // Planned is a decision as much as done is, so it goes through the
        // same door: it carries who decided and when, the reporters hear it
        // once (and a rewording once more), and the one deciding is not told
        // their own answer. Only a move back to open, or a plan with no words
        // yet, is built by hand.
        $moved = $status !== Gap::OPEN && trim($words) !== ''
            ? $gap->settled($status, $words, $principal->name)
            : $this->movedByHand($gap, $status, $words, $principal);

        return $this->previewOrExecute(
            $request,
            array_filter([
                'gap' => '#'.$gap->id.' '.$gap->title,
                'from' => $gap->status,
                'to' => $gap->status === $status ? $status.' (unchanged — rewording the resolution)' : $status,
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

    protected function movedByHand(Gap $gap, string $status, string $words, Principal $principal): Gap
    {
        $settling = $status !== Gap::OPEN;

        return new Gap(
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
            resolution: $words === '' ? null : $words,
            resolvedBy: $settling ? $principal->name : $gap->resolvedBy,
            resolvedAt: $settling ? now() : $gap->resolvedAt,
            reportedAt: $gap->reportedAt,
            id: $gap->id,
            followUps: $gap->followUps,
        );
    }

    /**
     * `gap` without a status: with a note it adds a follow-up, alone it
     * shows the gap as it stands, to the people who reported it and to
     * privileged staff.
     */
    protected function followUp(Request $request, Principal $principal): Response
    {
        $id = (string) $request->get('gap');
        $gap = app(GapStore::class)->find($id);

        if ($gap === null) {
            return Response::error("No gap with id {$id}.");
        }

        $note = trim((string) $request->get('note', ''));

        if ($note !== '') {
            return $this->addFollowUp($request, $principal, $gap, $note);
        }

        if (! app(FollowUps::class)->mayFollowUp($gap, $principal)) {
            return Response::error(sprintf("'%s' is for the people who reported it and the people who build this app to read. If you hit it too, report it with the same title and you join it.", $gap->title));
        }

        return Response::json([
            'gap' => array_filter([
                ...$gap->toArray(),
                'settled_by' => $gap->resolvedBy,
                // Colleagues do not read each other's notes; the builders do.
                'reported_by' => $principal->privileged ? $gap->reporters : null,
            ], static fn (mixed $value): bool => $value !== null),
            'to_add_to_it' => 'Call again with `gap` and `note`. Nothing has changed.',
        ]);
    }

    /**
     * One more note on a gap, in order beside the others.
     */
    protected function addFollowUp(Request $request, Principal $author, Gap $gap, string $text): Response
    {
        $followUps = app(FollowUps::class);
        $problem = $followUps->problem($gap, $author, $text);

        if ($problem !== null) {
            return Response::error($problem);
        }

        return $this->previewOrExecute(
            $request,
            [
                'gap' => '#'.$gap->id.' '.$gap->title,
                'status' => $gap->status.' (unchanged)',
                'follow_up' => trim($text),
                'written_by' => $author->name,
                'follow_ups_after' => count($gap->followUps) + 1,
                'goes_to' => $followUps->reaches($gap, $author),
            ],
            fn (): array => ['gap' => $followUps->add($gap, $author, $text)->toArray()],
            'Follow up on a gap',
        );
    }

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
