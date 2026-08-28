<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Gaps;

use HeiHallo\McpKit\Contracts\GapStore;
use HeiHallo\McpKit\Principal;

/**
 * Turning a description of something missing into a gap, deduplicated.
 *
 * Shared by `report_gap` and by closing a task frame, so the two cannot
 * drift: a repeat is joined rather than written twice, and one person
 * blocked makes the whole gap blocking either way.
 */
trait ComposesGaps
{
    /**
     * The gap this report becomes — a fresh one, or the open one it joins.
     */
    protected function composeGap(
        Principal $principal,
        string $title,
        string $need,
        string $missing,
        ?string $server = null,
        ?string $tool = null,
        bool $blocking = false,
        string $note = '',
    ): Gap {
        $existing = app(GapStore::class)->openMatching(Gap::key($title));

        return $existing === null
            ? new Gap(
                key: Gap::key($title),
                title: $title,
                need: $need,
                missing: $missing,
                server: $server,
                tool: $tool,
                blocking: $blocking,
                reporters: [$this->gapReporter($principal, $note)],
            )
            : $this->joinedGap($existing, $principal, $note, $blocking);
    }

    /**
     * @return array{name: string, at: string, note: string}
     */
    protected function gapReporter(Principal $principal, string $note): array
    {
        return [
            'name' => $principal->name,
            'at' => now()->toIso8601String(),
            'note' => $note,
        ];
    }

    protected function joinedGap(Gap $existing, Principal $principal, string $note, bool $blocking): Gap
    {
        return new Gap(
            key: $existing->key,
            title: $existing->title,
            need: $existing->need,
            missing: $existing->missing,
            server: $existing->server,
            tool: $existing->tool,
            // One person blocked is enough to call the whole gap blocking.
            blocking: $existing->blocking || $blocking,
            status: $existing->status,
            reporters: [...$existing->reporters, $this->gapReporter($principal, $note)],
            reports: $existing->reports + 1,
            resolution: $existing->resolution,
            resolvedBy: $existing->resolvedBy,
            resolvedAt: $existing->resolvedAt,
            reportedAt: $existing->reportedAt,
            id: $existing->id,
        );
    }
}
