<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Learning;

use HeiHallo\McpKit\Contracts\TaskStore;
use HeiHallo\McpKit\Principal;
use Throwable;

/**
 * The task frame a call belongs to. Resolved once per request and handed
 * to the activity stamper, so every row a call writes carries the same
 * task without a single tool having to know about it.
 */
class CurrentTask
{
    private bool $resolved = false;

    private ?Task $task = null;

    public function for(?Principal $principal): ?Task
    {
        if ($this->resolved) {
            return $this->task;
        }

        $this->resolved = true;

        $tokenId = $principal?->tokenId();

        if (! config('mcp-kit.learning.enabled', false) || $tokenId === null) {
            return $this->task = null;
        }

        try {
            return $this->task = app(TaskStore::class)->openFor((string) $tokenId);
        } catch (Throwable $e) {
            // An app that has not migrated yet still gets its call log.
            report($e);

            return $this->task = null;
        }
    }

    /**
     * A frame just opened or closed in this same request — keep the stamper
     * in step rather than letting it hand out a stale id.
     */
    public function set(?Task $task): void
    {
        $this->resolved = true;
        $this->task = $task;
    }
}
