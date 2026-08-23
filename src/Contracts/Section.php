<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Contracts;

use HeiHallo\McpKit\Principal;

/**
 * A ground-rules section computed at render time.
 */
interface Section
{
    public function render(?Principal $principal, ?string $server): string;
}
