<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Contracts;

use HeiHallo\McpKit\Gaps\Gap;

/**
 * Where reported gaps live. The default keeps them in a table and leaves
 * routing to the app, which listens for GapReported and turns it into
 * whatever it already uses — a task, an issue, a message.
 */
interface GapStore
{
    /**
     * The open one matching this key, if somebody already reported it.
     */
    public function openMatching(string $key): ?Gap;

    public function find(int|string $id): ?Gap;

    /**
     * @param  list<string>  $statuses
     * @return list<Gap>
     */
    public function list(array $statuses = [Gap::OPEN], ?string $server = null, int $limit = 50): array;

    public function put(Gap $gap): Gap;

    /**
     * Gaps this person reported that have been settled since they last
     * heard. Reading them is what marks them heard, so each answer is
     * given once.
     *
     * @return list<Gap>
     */
    public function settledFor(string $name): array;

    public function markHeard(Gap $gap, string $name): void;
}
