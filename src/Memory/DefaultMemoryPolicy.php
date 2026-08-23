<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Memory;

use HeiHallo\McpKit\Contracts\MemoryPolicy;
use HeiHallo\McpKit\Principal;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Yourself always; other people only if you are privileged; service
 * clients never.
 */
class DefaultMemoryPolicy implements MemoryPolicy
{
    public function view(Principal $actor, Authenticatable $subject): bool
    {
        if ($actor->isService() || $actor->blocked) {
            return false;
        }

        return $this->isSelf($actor, $subject) || $actor->privileged;
    }

    public function update(Principal $actor, Authenticatable $subject): bool
    {
        return $this->view($actor, $subject);
    }

    protected function isSelf(Principal $actor, Authenticatable $subject): bool
    {
        return $subject::class === $actor->tokenable::class
            && (string) $actor->id() === (string) $subject->getAuthIdentifier();
    }
}
