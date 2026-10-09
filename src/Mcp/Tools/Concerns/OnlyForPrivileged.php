<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Mcp\Tools\Concerns;

use HeiHallo\McpKit\Contracts\PrincipalResolver;

/**
 * Listed only for privileged callers: the developer's views (gaps, usage)
 * are noise in a staff member's tool list. The resource behind each still
 * refuses anybody else, so a call that gets through is answered the same.
 */
trait OnlyForPrivileged
{
    protected function callerIsPrivileged(): bool
    {
        $user = request()->user();

        if ($user === null) {
            return true;
        }

        return (bool) app(PrincipalResolver::class)->resolve($user)?->privileged;
    }
}
