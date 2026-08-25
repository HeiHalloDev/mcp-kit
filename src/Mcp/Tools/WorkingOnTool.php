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
 * Opens and closes a task frame. The call log already knows which tools
 * ran; this is what says why, and whether it worked.
 *
 * Deliberately does not preview and confirm. Everything else that writes
 * here changes the business's data and needs a person to agree; this
 * records the assistant's own account of its own session. Asking the
 * person to confirm it twice per task would buy nothing and cost enough
 * friction that nobody would do it.
 */
class WorkingOnTool extends StaffTool
{
    protected string $name = 'working_on';

    protected string $description = 'Say what you are about to do and why, before starting work that is more than a lookup — then call again with an outcome when it is over. Nothing is shown to the person; it is how the people who build this app learn what it is used for and where it falls short. Describe the kind of work, never the customer by name.';

    /**
     * @var array<string, mixed>
     */
    protected array $inputSchema = [
        'type' => 'object',
        'properties' => [
            'purpose' => ['type' => 'string', 'description' => 'One line: what the person wants and why, in the kind of terms they used. "Refunding a module a student bought twice", not the student\'s name.'],
            'outcome' => ['type' => 'string', 'enum' => ['done', 'partly', 'failed'], 'description' => 'Closing the frame: done when they got what they came for, partly when some of it, failed when none.'],
            'result' => ['type' => 'string', 'description' => 'Closing the frame: one line on what happened. Say plainly what got in the way when it did — that is the most useful thing here.'],
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
            : $this->open($request, $principal, (string) $tokenId);
    }

    protected function open(Request $request, Principal $principal, string $tokenId): Response
    {
        $purpose = trim((string) $request->get('purpose', ''));

        if ($purpose === '') {
            return Response::error('Say what you are about to do, in one line. Without it the calls that follow are just a list of tool names.');
        }

        $store = app(TaskStore::class);
        $store->abandonStale($tokenId, (int) config('mcp-kit.learning.lifetime_hours', 4));

        $superseded = $store->openFor($tokenId);
        $note = '';

        if ($superseded !== null) {
            // Moving on without closing means the last one is over and
            // nobody judged it. Say so rather than claiming it went well.
            $store->put($superseded->closedAs(Task::UNKNOWN, '', $store->countCalls($superseded)));
            $note = ' The previous frame was left open, so it is recorded as unknown — close them next time, especially the ones that did not work.';
        }

        $task = $store->put(new Task(
            purpose: $purpose,
            tokenId: $tokenId,
            userId: (string) $principal->id(),
            name: $principal->name,
            server: app(ServerRegistry::class)->forRoute(request()->route())?->key,
        ));

        app(CurrentTask::class)->set($task);

        event(new TaskOpened($task, $principal));

        return Response::text('Noted.'.$note.' Call working_on again with an outcome when this is over.');
    }

    protected function close(Request $request, Principal $principal, string $tokenId): Response
    {
        $outcome = (string) $request->get('outcome');

        if (! in_array($outcome, Task::OUTCOMES, true)) {
            return Response::error('Outcome is one of: '.implode(', ', Task::OUTCOMES).'.');
        }

        $store = app(TaskStore::class);
        $task = $store->openFor($tokenId);

        if ($task === null) {
            return Response::error('Nothing is open to close. Open a frame with purpose before the work, not after it.');
        }

        $result = trim((string) $request->get('result', ''));

        if ($result === '' && $outcome !== Task::DONE) {
            return Response::error('Say what got in the way. A task that fell short without a reason is the one row nobody can learn from.');
        }

        $closed = $store->put($task->closedAs($outcome, $result, $store->countCalls($task)));

        app(CurrentTask::class)->set(null);

        event(new TaskClosed($closed, $principal));

        return Response::text('Noted.');
    }

    public function shouldRegister(): bool
    {
        return (bool) config('mcp-kit.learning.enabled', false);
    }
}
