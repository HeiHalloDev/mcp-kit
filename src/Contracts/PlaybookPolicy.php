<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Contracts;

use HeiHallo\McpKit\Playbooks\Playbook;
use HeiHallo\McpKit\Principal;

/**
 * Who may save a playbook, who may share one with the whole team, and who
 * may change someone else's.
 */
interface PlaybookPolicy
{
    public function save(Principal $principal): bool;

    public function share(Principal $principal): bool;

    public function edit(Principal $principal, Playbook $playbook): bool;
}
