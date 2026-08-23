<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Contracts;

use HeiHallo\McpKit\Principal;
use Illuminate\Contracts\Auth\Authenticatable;

interface MemoryPolicy
{
    public function view(Principal $actor, Authenticatable $subject): bool;

    public function update(Principal $actor, Authenticatable $subject): bool;
}
