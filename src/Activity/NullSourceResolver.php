<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Activity;

use HeiHallo\McpKit\Contracts\ResolvesActivitySource;
use HeiHallo\McpKit\Principal;

/**
 * mcp-kit.activity.default_source, or nothing.
 */
class NullSourceResolver implements ResolvesActivitySource
{
    public function source(?Principal $principal, ?object $activity = null): ?string
    {
        $default = config('mcp-kit.activity.default_source');

        return is_string($default) && $default !== '' ? $default : null;
    }
}
