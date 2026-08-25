<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Events;

use HeiHallo\McpKit\Learning\Task;
use HeiHallo\McpKit\Principal;

final class TaskOpened
{
    public function __construct(
        public readonly Task $task,
        public readonly Principal $by,
    ) {}
}
