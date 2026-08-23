<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Events;

use HeiHallo\McpKit\Memory\AssistantMemory;
use HeiHallo\McpKit\Principal;
use Illuminate\Contracts\Auth\Authenticatable;

final class MemoryUpdated
{
    public function __construct(
        public readonly Authenticatable $subject,
        public readonly AssistantMemory $before,
        public readonly AssistantMemory $after,
        public readonly ?Principal $by,
        public readonly string $via,
    ) {}
}
