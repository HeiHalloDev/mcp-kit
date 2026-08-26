<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Mcp\Tools;

use HeiHallo\McpKit\Contracts\TaskStore;
use HeiHallo\McpKit\Events\TaskClosed;
use HeiHallo\McpKit\Events\TaskOpened;
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
 * that the work will matter — and it does not. Two rounds of instructions
 * produced zero frames against ninety-six calls. So the grouping is
 * automatic and this tool supplies the part only the assistant knows:
 * what it was for, whether the person got it, and how hard it was.
 *
 * Deliberately does not preview and confirm. Everything else that writes
 * here changes the business's data and needs a person to agree; this
 * records the assistant's own account of its own session.
 */
class WorkingOnTool extends StaffTool
{
    protected string $name = 'working_on';

    protected string $description = 'Say what a piece of work was for and how it went. Call it when the work is over — the frame is already open, this fills it in. `effort` is the judgement only you can make: smooth when the tools did what you needed, fiddly when it took stitching together, fought_it when it worked in the end and should not have been that hard. Succeeding after a fight is the most useful thing you can report, so say so. Describe the kind of work, never the customer.';

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
        ],
    ];

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

        return $request->get('outcome') !== null
            ? $this->close($request, $principal, (string) $tokenId)
            : $this->nameFrame($request, $principal, (string) $tokenId);
    }

    /**
     * Naming without closing: the work is still going, but now the frame
     * says what it is.
     */
    protected function nameFrame(Request $request, Principal $principal, string $tokenId): Response
    {
        $purpose = trim((string) $request->get('purpose', ''));

        if ($purpose === '') {
            return Response::error('Say what this work is for, in one line — or pass an outcome and an effort to close it.');
        }

        $store = app(TaskStore::class);
        $task = $this->openFrame($store, $principal, $tokenId);

        $named = $store->put($task->named($purpose));

        app(CurrentTask::class)->set($named);

        event(new TaskOpened($named, $principal));

        return Response::text('Noted. Call working_on again with an outcome and an effort when this is over.');
    }

    protected function close(Request $request, Principal $principal, string $tokenId): Response
    {
        $outcome = (string) $request->get('outcome');

        if (! in_array($outcome, Task::OUTCOMES, true)) {
            return Response::error('Outcome is one of: '.implode(', ', Task::OUTCOMES).'.');
        }

        $effort = trim((string) $request->get('effort', ''));

        if (! in_array($effort, Task::EFFORTS, true)) {
            return Response::error(
                'Say how hard it was: '.implode(', ', Task::EFFORTS).'. This is the judgement only you can make — '
                .'the call count cannot see it, because reading before writing and previewing before confirming '
                .'make a correct write three calls by design. A task that got there in the end but should not '
                .'have been that hard is fought_it, and that is the row worth reading.'
            );
        }

        $store = app(TaskStore::class);
        $task = $this->openFrame($store, $principal, $tokenId);

        $result = trim((string) $request->get('result', ''));

        if ($result === '' && ($outcome !== Task::DONE || $effort !== Task::SMOOTH)) {
            return Response::error('Say what got in the way. A task that fell short, or that was harder than it should have been, is the one row nobody can learn from without a reason.');
        }

        $closed = $store->put($task->closedAs(
            outcome: $outcome,
            result: $result,
            calls: $task->calls,
            effort: $effort,
            purpose: trim((string) $request->get('purpose', '')),
        ));

        app(CurrentTask::class)->set(null);

        event(new TaskClosed($closed, $principal));

        return Response::text($closed->isUnnamed()
            ? 'Noted — though the frame has no purpose on it, so it says how it went without saying what it was.'
            : 'Noted.');
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
