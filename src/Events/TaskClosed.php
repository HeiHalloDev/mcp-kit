<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Events;

use HeiHallo\McpKit\Learning\Task;
use HeiHallo\McpKit\Principal;

/**
 * A task frame was judged. `$task->fellShort()` is the interesting case:
 * somebody did not get what they came for.
 */
final class TaskClosed
{
    public function __construct(
        public readonly Task $task,
        public readonly ?Principal $by,
    ) {}
}
