<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Contracts;

use HeiHallo\McpKit\Learning\Task;

/**
 * Where task frames live: what the assistant said it was doing, and how it
 * turned out.
 */
interface TaskStore
{
    /**
     * The task this token has open, if any. Called on every MCP request, so
     * it must stay one indexed lookup.
     */
    public function openFor(string $tokenId): ?Task;

    public function put(Task $task): Task;

    /**
     * Record that a call belonged to this frame; returns the new total.
     */
    public function noteCall(Task $task): int;

    /**
     * @param  list<string>  $outcomes
     * @return list<Task>
     */
    public function recent(array $outcomes = [], int $limit = 100, int $days = 30): array;

    /**
     * Close whatever this token left open, as unknown. Nobody said how it
     * went, so nothing is claimed about it.
     */
    public function abandonStale(string $tokenId, int $olderThanHours): void;

    public function countCalls(Task $task): int;

    public function prune(int $days): int;
}
