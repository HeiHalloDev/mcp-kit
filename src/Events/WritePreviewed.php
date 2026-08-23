<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Events;

use HeiHallo\McpKit\Principal;

final class WritePreviewed
{
    /**
     * @param  array<string, mixed>  $preview
     */
    public function __construct(
        public readonly Principal $principal,
        public readonly string $tool,
        public readonly string $action,
        public readonly array $preview,
    ) {}
}
