<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Events;

use HeiHallo\McpKit\Playbooks\Playbook;
use HeiHallo\McpKit\Principal;
use Illuminate\Contracts\Auth\Authenticatable;

final class PlaybookSaved
{
    public function __construct(
        public readonly Authenticatable $owner,
        public readonly Playbook $playbook,
        public readonly ?Principal $by,
    ) {}
}
