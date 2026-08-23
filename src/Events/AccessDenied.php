<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Events;

use HeiHallo\McpKit\Principal;
use Illuminate\Http\Request;

final class AccessDenied
{
    public function __construct(
        public readonly ?Principal $principal,
        public readonly string $reason,
        public readonly Request $request,
    ) {}
}
