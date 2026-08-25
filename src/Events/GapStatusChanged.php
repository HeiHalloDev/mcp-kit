<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Events;

use HeiHallo\McpKit\Gaps\Gap;
use HeiHallo\McpKit\Principal;

final class GapStatusChanged
{
    public function __construct(
        public readonly Gap $gap,
        public readonly string $from,
        public readonly Principal $by,
    ) {}
}
