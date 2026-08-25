<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Playbooks;

use HeiHallo\McpKit\Contracts\PlaybookPolicy;
use HeiHallo\McpKit\Principal;

/**
 * Anyone may keep their own playbooks. Sharing one with everybody is a
 * privileged act, and nobody edits someone else's.
 */
class DefaultPlaybookPolicy implements PlaybookPolicy
{
    public function save(Principal $principal): bool
    {
        return $principal->isPerson() && ! $principal->blocked;
    }

    public function share(Principal $principal): bool
    {
        return $this->save($principal) && $principal->privileged;
    }

    public function edit(Principal $principal, Playbook $playbook): bool
    {
        return $this->save($principal)
            && (string) $playbook->userId === (string) $principal->id();
    }
}
