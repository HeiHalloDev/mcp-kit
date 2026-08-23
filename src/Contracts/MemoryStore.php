<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Contracts;

use HeiHallo\McpKit\Memory\AssistantMemory;
use Illuminate\Contracts\Auth\Authenticatable;

interface MemoryStore
{
    public function get(Authenticatable $user): AssistantMemory;

    public function put(Authenticatable $user, AssistantMemory $memory): void;
}
