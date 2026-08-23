<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Audit;

use HeiHallo\McpKit\Principal;

/**
 * One confirmed write, as the confirm helper saw it.
 */
final class WriteRecord
{
    /**
     * @param  array<string, mixed>  $arguments  sanitised
     * @param  array<string, mixed>  $details  the preview that was confirmed
     * @param  array<string, mixed>  $result  summarised
     */
    public function __construct(
        public readonly Principal $principal,
        public readonly string $tool,
        public readonly string $action,
        public readonly array $arguments,
        public readonly array $details,
        public readonly array $result,
        public readonly ?object $subject,
        public readonly ?string $reason,
        public readonly ?string $callId,
    ) {}
}
