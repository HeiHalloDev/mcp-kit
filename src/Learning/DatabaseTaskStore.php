<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Learning;

use HeiHallo\McpKit\Contracts\TaskStore;
use HeiHallo\McpKit\Models\Task as TaskModel;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

/**
 * Task frames in the mcp_tasks table.
 */
class DatabaseTaskStore implements TaskStore
{
    public function openFor(string $tokenId): ?Task
    {
        $row = $this->model()->newQuery()
            ->where('token_id', $tokenId)
            ->where('outcome', Task::OPEN)
            ->latest('id')
            ->first();

        return $row === null ? null : $this->toTask($row);
    }

    public function put(Task $task): Task
    {
        $attributes = [
            'token_id' => $task->tokenId,
            'user_id' => (string) $task->userId,
            'name' => $task->name,
            'purpose' => $task->purpose,
            'server' => $task->server,
            'outcome' => $task->outcome,
            'result' => $task->result,
            'effort' => $task->effort,
            'calls' => $task->calls,
            'refusals' => $task->refusals,
            'closed_at' => $task->closedAt,
        ];

        $row = $task->id === null
            ? $this->model()->newQuery()->create($attributes)
            : tap($this->model()->newQuery()->findOrFail($task->id))->update($attributes);

        return $this->toTask($row);
    }

    public function recent(array $outcomes = [], int $limit = 100, int $days = 30, ?string $person = null): array
    {
        $rows = $this->model()->newQuery()
            ->when($outcomes !== [], fn ($query) => $query->whereIn('outcome', $outcomes))
            ->when($person !== null && trim($person) !== '', function ($query) use ($person): void {
                $person = trim((string) $person);

                $query->where(fn ($q) => $q
                    ->whereRaw('lower(name) like ?', ['%'.mb_strtolower($person).'%'])
                    ->orWhere('user_id', $person));
            })
            ->where('created_at', '>=', now()->subDays($days))
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return $this->withTools($rows->map(fn (TaskModel $row): Task => $this->toTask($row))->all());
    }

    /**
     * An unnamed frame is a blank line unless something says what it was.
     * The activity log does: every call carries the frame id. One query for
     * the whole page, tallied here so it runs the same on every database.
     *
     * @param  list<Task>  $tasks
     * @return list<Task>
     */
    protected function withTools(array $tasks): array
    {
        $unnamed = array_values(array_filter($tasks, fn (Task $t): bool => $t->isUnnamed() && $t->id !== null));

        if ($unnamed === []) {
            return $tasks;
        }

        $model = (string) config('activitylog.activity_model', Activity::class);
        $table = (new $model)->getTable();
        $tally = [];

        DB::table($table)
            ->where('log_name', (string) config('mcp-kit.activity.log_name', 'mcp'))
            ->whereIn('properties->task', array_map(fn (Task $t): string => (string) $t->id, $unnamed))
            ->orderBy('id')
            ->select(['id', 'properties'])
            ->chunk(500, function ($rows) use (&$tally): void {
                foreach ($rows as $row) {
                    $properties = json_decode((string) $row->properties, true);
                    $task = $properties['task'] ?? null;
                    $tool = $properties['tool'] ?? null;

                    if ($task !== null && is_string($tool) && $tool !== '') {
                        $tally[(string) $task][$tool] = ($tally[(string) $task][$tool] ?? 0) + 1;
                    }
                }
            });

        return array_map(
            fn (Task $t): Task => isset($tally[(string) $t->id]) ? $t->withTools($tally[(string) $t->id]) : $t,
            $tasks,
        );
    }

    /**
     * Count this call against the open frame and hand back the new total.
     * Kept on the row rather than counted from the log, because the nudge
     * has to know mid-call and a count query per call is not worth it.
     */
    public function noteCall(Task $task): int
    {
        $row = $this->model()->newQuery()->find($task->id);

        if ($row === null) {
            return $task->calls;
        }

        $row->forceFill(['calls' => $row->calls + 1])->saveQuietly();

        return (int) $row->calls;
    }

    public function noteRefusal(Task $task): int
    {
        $row = $this->model()->newQuery()->find($task->id);

        if ($row === null) {
            return $task->refusals;
        }

        $row->forceFill(['refusals' => (int) $row->refusals + 1])->saveQuietly();

        return (int) $row->refusals;
    }

    /**
     * A frame goes stale when nobody has called a tool in it for a while —
     * the work has stopped — or when it has simply run too long, which is
     * what catches a token shared by parallel threads that never goes quiet.
     *
     * It ends at its last call, not at the moment somebody came back and
     * found it stale: that can be days later, and a span running to it reads
     * as days of work. Every call touches updated_at, so it holds the last
     * one; the base query leaves it alone while copying it.
     */
    public function abandonStale(string $tokenId, int $olderThanHours, ?int $idleMinutes = null): void
    {
        $query = $this->model()->newQuery()
            ->where('token_id', $tokenId)
            ->where('outcome', Task::OPEN)
            ->where(fn ($q) => $q
                ->where('created_at', '<', now()->subHours($olderThanHours))
                ->when($idleMinutes !== null && $idleMinutes > 0, fn ($q) => $q->orWhere('updated_at', '<', now()->subMinutes((int) $idleMinutes))))
            ->toBase();

        $query->update([
            'outcome' => Task::UNKNOWN,
            'closed_at' => DB::raw($query->getGrammar()->wrap('updated_at')),
        ]);
    }

    /**
     * How many tool calls carried this task's stamp. Read from the activity
     * log rather than counted as we go, so a crashed session still gets a
     * true number.
     */
    public function countCalls(Task $task): int
    {
        $model = (string) config('activitylog.activity_model', Activity::class);
        $table = (new $model)->getTable();

        return DB::table($table)
            ->where('log_name', (string) config('mcp-kit.activity.log_name', 'mcp'))
            ->where('properties->task', (string) $task->id)
            ->count();
    }

    public function prune(int $days): int
    {
        return $this->model()->newQuery()
            ->where('created_at', '<', now()->subDays($days))
            ->delete();
    }

    protected function toTask(TaskModel $row): Task
    {
        return new Task(
            purpose: (string) $row->purpose,
            tokenId: (string) $row->token_id,
            userId: (string) $row->user_id,
            name: (string) $row->name,
            server: $row->server === null ? null : (string) $row->server,
            outcome: (string) $row->outcome,
            result: $row->result === null ? null : (string) $row->result,
            effort: $row->effort === null ? null : (string) $row->effort,
            calls: (int) $row->calls,
            refusals: (int) ($row->refusals ?? 0),
            startedAt: $row->created_at,
            closedAt: $row->closed_at,
            lastCallAt: $row->updated_at,
            id: $row->id,
        );
    }

    protected function model(): TaskModel
    {
        $class = (string) config('mcp-kit.learning.model', TaskModel::class);

        return new $class;
    }
}
