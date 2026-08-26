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
     * Open a frame for this caller if none is open, and count this call
     * against it. Nobody is asked to do this: a model cannot reliably
     * judge, before starting, that work will be worth framing — two
     * attempts at instructing it produced zero frames against ninety-six
     * calls. So the grouping happens on its own and only the *naming* is
     * asked for, afterwards, when the assistant knows what it did.
     *
     * Returns the call's new position in the frame, or null when there is
     * no frame to belong to.
     */
    public function ensureOpen(?Principal $principal, ?string $server): ?int
    {
        if (! config('mcp-kit.learning.enabled', false)) {
            return null;
        }

        $tokenId = $principal?->tokenId();

        if ($tokenId === null || ! $principal->isPerson() || $principal->blocked) {
            return null;
        }

        try {
            $store = app(TaskStore::class);
            $store->abandonStale((string) $tokenId, (int) config('mcp-kit.learning.lifetime_hours', 4));

            $task = $store->openFor((string) $tokenId);

            if ($task === null) {
                $task = $store->put(new Task(
                    purpose: '',
                    tokenId: (string) $tokenId,
                    userId: (string) $principal->id(),
                    name: $principal->name,
                    server: $server,
                ));
            }

            $this->set($task);

            return $store->noteCall($task);
        } catch (Throwable $e) {
            // An app that has not migrated yet still gets its call log.
            if (! self::$reported) {
                self::$reported = true;
                report($e);
            }

            return null;
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
