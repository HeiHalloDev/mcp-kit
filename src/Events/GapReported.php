<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Events;

use HeiHallo\McpKit\Gaps\Gap;
use HeiHallo\McpKit\Principal;

/**
 * A gap was filed, or somebody said they hit an existing one. Listen for
 * this to route it where your team actually looks — a task, an issue, a
 * message. `$isNew` is false when an existing gap simply gained weight.
 */
final class GapReported
{
    public function __construct(
        public readonly Gap $gap,
        public readonly Principal $by,
        public readonly bool $isNew,
    ) {}
}
