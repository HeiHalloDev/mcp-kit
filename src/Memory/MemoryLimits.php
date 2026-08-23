<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Memory;

final class MemoryLimits
{
    public function __construct(
        public readonly int $routines = 12,
        public readonly int $handoffs = 12,
        public readonly int $notes = 20,
        public readonly int $itemChars = 240,
        public readonly int $roleChars = 280,
        public readonly int $maxBytes = 8192,
    ) {}

    public static function fromConfig(): self
    {
        $limits = (array) config('mcp-kit.memory.limits', []);

        return new self(
            routines: (int) ($limits['routines'] ?? 12),
            handoffs: (int) ($limits['handoffs'] ?? 12),
            notes: (int) ($limits['notes'] ?? 20),
            itemChars: (int) ($limits['item_chars'] ?? 240),
            roleChars: (int) ($limits['role_chars'] ?? 280),
            maxBytes: (int) config('mcp-kit.memory.max_bytes', 8192),
        );
    }
}
