<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Mcp\Tools;

use HeiHallo\McpKit\Contracts\GapStore;
use HeiHallo\McpKit\Contracts\TaskStore;
use HeiHallo\McpKit\Events\GapReported;
use HeiHallo\McpKit\Events\TaskClosed;
use HeiHallo\McpKit\Events\TaskOpened;
use HeiHallo\McpKit\Gaps\ComposesGaps;
use HeiHallo\McpKit\Gaps\Gap;
use HeiHallo\McpKit\Learning\CurrentTask;
use HeiHallo\McpKit\Learning\Task;
use HeiHallo\McpKit\Memory\SecretDetector;
use HeiHallo\McpKit\Principal;
use HeiHallo\McpKit\Servers\ServerRegistry;
use HeiHallo\McpKit\Tools\StaffTool;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

/**
 * Names a piece of work and says how it went.
 *
 * The frame itself is opened by the middleware on the first call, because
 * asking an assistant to open one *before* the work requires it to predict
 * that the work will matter — and it does not. So the grouping is automatic
 * and this tool supplies the part only the assistant knows: what it was
 * for, whether the person got it, and how hard it was.
 *
 * Every refusal here names the parameter it is talking about. The first
 * assistant to use this in earnest tried seven times and never got in: it
 * fixed `effort` after one refusal, because that message lists the valid
 * values, and never found `purpose` or `result`, because those messages
 * asked for a thing without saying what it was called. An error that
 * cannot be acted on is worse than no error.
 *
 * Deliberately does not preview and confirm. Everything else that writes
 * here changes the business's data and needs a person to agree; this
 * records the assistant's own account of its own session.
 */
class WorkingOnTool extends StaffTool
{
    use ComposesGaps;

    protected string $name = 'working_on';

    protected string $description = 'Say what a piece of work was for and how it went. Call it when the work is over — the frame is already open, this fills it in. Parameters: `purpose` (what it was for), `outcome` (done, partly, failed), `effort` (smooth, fiddly, fought_it) and `result` (what happened, and what got in the way). When the work was harder than it should have been because something is missing, name it in `gap` and it is filed in the same call. `effort` is the judgement only you can make: smooth when the tools did what you needed, fiddly when it took stitching together, fought_it when it worked in the end and should not have been that hard. Succeeding after a fight is the most useful thing you can report, so say so. Describe the kind of work, never the customer.';

    /**
     * @var array<string, mixed>
     */
    protected array $inputSchema = [
        'type' => 'object',
        'properties' => [
            'purpose' => ['type' => 'string', 'description' => 'One line: what the person wanted and why, in the kind of terms they used. "Refunding a module a student bought twice", not the student\'s name.'],
            'outcome' => ['type' => 'string', 'enum' => ['done', 'partly', 'failed'], 'description' => 'done when they got what they came for, partly when some of it, failed when none.'],
            'effort' => ['type' => 'string', 'enum' => ['smooth', 'fiddly', 'fought_it'], 'description' => 'How hard it was to get there, regardless of whether you got there. Do not flatter the tools: a task that succeeded after twenty minutes of working around them is fought_it, not smooth, and that row is the point of this whole record.'],
            'result' => ['type' => 'string', 'description' => 'One line on what happened. Say plainly what got in the way when something did — that is the most useful thing here.'],
            'gap' => ['type' => 'string', 'description' => 'If the work was hard because this app cannot do something yet, a short title for the missing capability — "no way to list a sequence\'s scheduled messages". Files it for whoever builds this app, using the purpose and result above. Leave it out when the friction was your own doing rather than a missing tool.'],
        ],
    ];

    /**
     * Names assistants reach for instead of the real ones. Taken from what
     * they actually sent, not from imagination. The value is accepted and
     * the reply says which parameter it went into, so the next call is
     * right rather than merely forgiven.
     *
     * @var array<string, list<string>>
     */
    protected const ALSO_KNOWN_AS = [
        'purpose' => ['task', 'work', 'doing', 'summary', 'what'],
        'result' => ['friction', 'shortfall', 'reason', 'note', 'notes', 'details', 'what_happened'],
    ];

    /** Parameters read under a name that is not theirs, for the reply. */
    protected array $misnamed = [];

    /** The frame every refusal is counted against. */
    protected ?Task $frame = null;

    public function handle(Request $request): Response
    {
        $principal = $this->requirePerson($request);

        if ($principal instanceof Response) {
            return $principal;
        }

        if (! config('mcp-kit.learning.enabled', false)) {
            return Response::error('This app does not record what its tools are used for.');
        }

        $tokenId = $principal->tokenId();

        if ($tokenId === null) {
            return Response::error('A task frame belongs to a token, and this call has none.');
        }

        if (SecretDetector::looksSecret($request->all())) {
            return Response::error('That looks like a password or token. A task frame says what kind of work is going on, never a credential.');
        }

        $this->misnamed = [];
        $this->frame = $this->openFrame(app(TaskStore::class), $principal, (string) $tokenId);

        return $request->get('outcome') !== null
            ? $this->close($request, $principal)
            : $this->nameFrame($request, $principal);
    }

    /**
     * Naming without closing: the work is still going, but now the frame
     * says what it is.
     */
    protected function nameFrame(Request $request, Principal $principal): Response
    {
        $purpose = $this->read($request, 'purpose');

        if ($purpose === '') {
            return $this->refuse('`purpose` is missing. Pass `purpose` with one line on what this work is for — or pass `outcome` and `effort` to close it.');
        }

        $store = app(TaskStore::class);
        $named = $store->put($this->frame->named($purpose));

        app(CurrentTask::class)->set($named);

        event(new TaskOpened($named, $principal));

        return Response::text($this->say('Noted. Call working_on again with `outcome` and `effort` when this is over.'));
    }

    protected function close(Request $request, Principal $principal): Response
    {
        $outcome = (string) $request->get('outcome');

        if (! in_array($outcome, Task::OUTCOMES, true)) {
            return $this->refuse('`outcome` is one of: '.implode(', ', Task::OUTCOMES).'.');
        }

        $effort = trim((string) $request->get('effort', ''));

        if (! in_array($effort, Task::EFFORTS, true)) {
            return $this->refuse(
                '`effort` is one of: '.implode(', ', Task::EFFORTS).'. This is the judgement only you can make — '
                .'the call count cannot see it, because reading before writing and previewing before confirming '
                .'make a correct write three calls by design. A task that got there in the end but should not '
                .'have been that hard is fought_it, and that is the row worth reading.'
            );
        }

        $result = $this->read($request, 'result');

        if ($result === '' && ($outcome !== Task::DONE || $effort !== Task::SMOOTH)) {
            return $this->refuse('`result` is missing. Pass `result` with one line on what happened and what got in the way. A task that fell short, or that was harder than it should have been, is the one row nobody can learn from without a reason.');
        }

        $store = app(TaskStore::class);

        $closed = $store->put($this->frame->closedAs(
            outcome: $outcome,
            result: $result,
            calls: $this->frame->calls,
            effort: $effort,
            purpose: $this->read($request, 'purpose'),
        ));

        app(CurrentTask::class)->set(null);

        event(new TaskClosed($closed, $principal));

        $reply = $closed->isUnnamed()
            ? 'Noted — though the frame has no `purpose` on it, so it says how it went without saying what it was.'
            : 'Noted.';

        return Response::text($this->say($reply.$this->gapFrom($request, $closed, $principal)));
    }

    /**
     * File the missing capability the work ran into, if the assistant named
     * one — in the same call that closes the frame.
     *
     * `report_gap` went unused: zero gaps against 378 calls, while seven
     * frames described missing capability in their `result`. Describing
     * friction on the way out is natural; deciding to file a separate
     * report is not, the same asymmetry that stopped anyone opening a frame
     * before the middleware did it for them.
     *
     * Only for work that actually fought back: a smooth task naming a gap
     * is a contradiction, and a gap needs a reason to read.
     */
    protected function gapFrom(Request $request, Task $closed, Principal $principal): string
    {
        $title = Gap::title((string) $request->get('gap', ''));

        if (! config('mcp-kit.gaps.enabled', true)) {
            return '';
        }

        // Nothing named, but the work fought back and said why: ask, here,
        // where the sentence has just been written. Asking later, through a
        // separate tool, is what nobody did.
        if ($title === '') {
            return $closed->wasHarderThanItShouldBe() && trim((string) $closed->result) !== ''
                ? ' If it was hard because this app cannot do something yet, report_gap it now — you have already written the sentence. Next time, pass `gap` here and it is filed in the same call.'
                : '';
        }

        if (! $closed->wasHarderThanItShouldBe()) {
            return ' (No gap filed: `gap` is for work that fought back, and this was `'.$closed->effort.'`.)';
        }

        if ($closed->result === null || trim($closed->result) === '') {
            return ' (No gap filed: it needs a `result` saying what got in the way.)';
        }

        // Checked before composing: composeGap joins this person on, so
        // asking the composed gap whether they reported it always says yes.
        try {
            $already = app(GapStore::class)->openMatching(Gap::key($title));
        } catch (\InvalidArgumentException $e) {
            return ' (No gap filed: '.$e->getMessage().')';
        }

        if ($already !== null && $already->reportedBy($principal->name)) {
            return ' You have already reported that one, so nothing was added.';
        }

        try {
            $gap = $this->composeGap(
                $principal,
                title: $title,
                need: $closed->purpose !== '' ? $closed->purpose : $title,
                missing: $closed->result,
                server: app(ServerRegistry::class)->forRoute(request()->route())?->key,
            );
        } catch (\InvalidArgumentException $e) {
            return ' (No gap filed: '.$e->getMessage().')';
        }

        $isNew = $gap->id === null;
        $saved = app(GapStore::class)->put($gap);

        event(new GapReported($saved, $principal, $isNew));

        return $isNew
            ? ' Filed as a gap for whoever builds this app.'
            : sprintf(' Added to an open gap, now %d reports behind it.', $saved->reports);
    }

    /**
     * Read a parameter, falling back to the names assistants reach for
     * instead. What was used is remembered so the reply can name the real
     * one — accepted, not silently swallowed.
     */
    protected function read(Request $request, string $parameter): string
    {
        $value = trim((string) $request->get($parameter, ''));

        if ($value !== '') {
            return $value;
        }

        foreach (self::ALSO_KNOWN_AS[$parameter] ?? [] as $alias) {
            $value = trim((string) $request->get($alias, ''));

            if ($value !== '') {
                $this->misnamed[$alias] = $parameter;

                return $value;
            }
        }

        return '';
    }

    /**
     * Refusals are counted on the frame. Seven in a row look identical to
     * a frame nobody touched, and they are the opposite: somebody tried.
     */
    protected function refuse(string $message): Response
    {
        if ($this->frame !== null) {
            app(TaskStore::class)->noteRefusal($this->frame);
        }

        return Response::error($this->say($message));
    }

    protected function say(string $message): string
    {
        foreach ($this->misnamed as $alias => $parameter) {
            $message .= sprintf(' (`%s` is not a parameter here — I read it as `%s`.)', $alias, $parameter);
        }

        return $message;
    }

    /**
     * The middleware opens a frame on the first call, so there is normally
     * one waiting. It can still be missing — a first call that was this
     * one, or an app that turned recording on mid-session.
     */
    protected function openFrame(TaskStore $store, Principal $principal, string $tokenId): Task
    {
        return $store->openFor($tokenId) ?? $store->put(new Task(
            purpose: '',
            tokenId: $tokenId,
            userId: (string) $principal->id(),
            name: $principal->name,
            server: app(ServerRegistry::class)->forRoute(request()->route())?->key,
        ));
    }

    public function shouldRegister(): bool
    {
        return (bool) config('mcp-kit.learning.enabled', false);
    }
}
