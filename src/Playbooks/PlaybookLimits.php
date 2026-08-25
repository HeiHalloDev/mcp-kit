<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Playbooks;

/**
 * The caps a playbook has to fit inside. Over one of them the tool refuses
 * and says which; nothing is ever silently trimmed.
 */
final class PlaybookLimits
{
    public function __construct(
        public readonly int $perPerson = 30,
        public readonly int $bodyChars = 4000,
        public readonly int $titleChars = 80,
        public readonly int $descriptionChars = 200,
        public readonly int $arguments = 8,
    ) {}

    public static function fromConfig(): self
    {
        $limits = (array) config('mcp-kit.playbooks.limits', []);

        return new self(
            perPerson: (int) ($limits['per_person'] ?? 30),
            bodyChars: (int) ($limits['body_chars'] ?? 4000),
            titleChars: (int) ($limits['title_chars'] ?? 80),
            descriptionChars: (int) ($limits['description_chars'] ?? 200),
            arguments: (int) ($limits['arguments'] ?? 8),
        );
    }
}
