<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Events;

use HeiHallo\McpKit\Gaps\Gap;
use HeiHallo\McpKit\Principal;

/**
 * Somebody added to a gap after it was filed: a retest, a new example, a
 * question back or the answer to one. The gap carries the whole thread;
 * `$text` is the note that was just written. The kit already delivers it to
 * the other side as a message, so listen only to route it somewhere else.
 */
final class GapFollowedUp
{
    public function __construct(
        public readonly Gap $gap,
        public readonly Principal $by,
        public readonly string $text,
    ) {}
}
