<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Contracts;

use HeiHallo\McpKit\Principal;
use Illuminate\Contracts\Auth\Authenticatable;

interface PrincipalResolver
{
    /**
     * Null when the tokenable is neither a person nor a service client.
     */
    public function resolve(Authenticatable $tokenable): ?Principal;
}
