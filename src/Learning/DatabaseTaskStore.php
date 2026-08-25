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
            'calls' => $task->calls,
            'closed_at' => $task->closedAt,
        ];

        $row = $task->id === null
            ? $this->model()->newQuery()->create($attributes)
            : tap($this->model()->newQuery()->findOrFail($task->id))->update($attributes);

        return $this->toTask($row);
    }

    public function recent(array $outcomes = [], int $limit = 100, int $days = 30): array
    {
        $rows = $this->model()->newQuery()
            ->when($outcomes !== [], fn ($query) => $query->whereIn('outcome', $outcomes))
            ->where('created_at', '>=', now()->subDays($days))
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return $rows->map(fn (TaskModel $row): Task => $this->toTask($row))->all();
    }

    public function abandonStale(string $tokenId, int $olderThanHours): void
    {
        $this->model()->newQuery()
            ->where('token_id', $tokenId)
            ->where('outcome', Task::OPEN)
            ->where('created_at', '<', now()->subHours($olderThanHours))
            ->update(['outcome' => Task::UNKNOWN, 'closed_at' => now()]);
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
            calls: (int) $row->calls,
            startedAt: $row->created_at,
            closedAt: $row->closed_at,
            id: $row->id,
        );
    }

    protected function model(): TaskModel
    {
        $class = (string) config('mcp-kit.learning.model', TaskModel::class);

        return new $class;
    }
}
