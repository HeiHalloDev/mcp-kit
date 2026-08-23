<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Events;

use HeiHallo\McpKit\Principal;

final class AbilityDenied
{
    public function __construct(
        public readonly ?Principal $principal,
        public readonly string $ability,
        public readonly string $reason,
        public readonly ?string $tool,
    ) {}
}
