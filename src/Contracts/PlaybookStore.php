<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Contracts;

use HeiHallo\McpKit\Playbooks\Playbook;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Where saved playbooks live. The default keeps them in a table; an app
 * that already has a snippet library points this at its own storage.
 */
interface PlaybookStore
{
    /**
     * Everything this person may run: their own, plus what colleagues shared.
     *
     * @return list<Playbook>
     */
    public function visibleTo(Authenticatable $user): array;

    /**
     * Only the ones this person wrote.
     *
     * @return list<Playbook>
     */
    public function ownedBy(Authenticatable $user): array;

    public function find(Authenticatable $user, string $name): ?Playbook;

    public function put(Authenticatable $user, Playbook $playbook): Playbook;

    public function forget(Authenticatable $user, string $name): bool;

    /**
     * Bump the counters after a client asked for the playbook's text.
     */
    public function recordUse(Authenticatable $user, string $name): void;
}
