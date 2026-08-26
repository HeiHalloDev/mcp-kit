<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Mcp\Resources;

use HeiHallo\McpKit\Contracts\PrincipalResolver;
use HeiHallo\McpKit\Contracts\TaskStore;
use HeiHallo\McpKit\Learning\Task;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Resource;

/**
 * What people actually came here to do, and how often they got it. For
 * whoever builds the app: it is a record of colleagues' work, so it is
 * privileged, the same as the gap list.
 */
class UsageResource extends Resource
{
    protected string $name = 'usage';

    protected string $description = 'For whoever builds this app: what people came here to do lately, in their own terms, and whether they got it. The ones that fell short are listed first — read them against the gap list. Privileged staff only.';

    protected string $mimeType = 'text/markdown';

    public function uri(): string
    {
        return config('mcp-kit.scheme', 'app').'://usage';
    }

    public function shouldRegister(): bool
    {
        return (bool) config('mcp-kit.learning.enabled', false);
    }

    public function handle(Request $request): Response
    {
        $tokenable = $request->user();
        $principal = $tokenable ? app(PrincipalResolver::class)->resolve($tokenable) : null;

        if ($principal === null) {
            return Response::error('Authentication required.');
        }

        if (! $principal->privileged) {
            return Response::error('What everybody used this for is a developer\'s view. It is a record of colleagues\' work, not a leaderboard.');
        }

        $store = app(TaskStore::class);
        $days = (int) config('mcp-kit.learning.recent_days', 30);
        $recent = $store->recent(limit: (int) config('mcp-kit.learning.recent_limit', 100), days: $days);

        return Response::text(view('mcp-kit::resources.usage', [
            'days' => $days,
            // An assistant tried to say what it was doing and could not
            // get in. Leads the file: it means the record is lying about
            // itself, and every number under it is short.
            'refused' => array_values(array_filter($recent, fn (Task $t): bool => $t->namingWasRefused())),
            'shortfalls' => array_values(array_filter($recent, fn (Task $t): bool => $t->fellShort())),
            // Succeeded, but not easily. No outcome flags these and no
            // call count finds them; the effort judgement is the only
            // thing that can see them.
            'hard_won' => array_values(array_filter(
                $recent,
                fn (Task $t): bool => $t->outcome === Task::DONE && $t->wasHarderThanItShouldBe(),
            )),
            'done' => array_values(array_filter(
                $recent,
                fn (Task $t): bool => $t->outcome === Task::DONE && ! $t->wasHarderThanItShouldBe(),
            )),
            'unjudged' => array_values(array_filter(
                $recent,
                fn (Task $t): bool => in_array($t->outcome, [Task::OPEN, Task::UNKNOWN], true),
            )),
        ])->render());
    }
}
