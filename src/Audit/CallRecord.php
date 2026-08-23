<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Audit;

use HeiHallo\McpKit\Principal;

/**
 * One tools/call, as the middleware saw it.
 */
final class CallRecord
{
    /**
     * @param  array<string, mixed>  $arguments  already sanitised
     */
    public function __construct(
        public readonly string $callId,
        public readonly string $method,
        public readonly ?string $tool,
        public readonly array $arguments,
        public readonly ?Principal $principal,
        public readonly ?string $server,
        public readonly string $status,
        public readonly ?string $action,
        public readonly ?string $denial,
        public readonly ?string $deniedAbility,
        public readonly float $durationMs,
        public readonly int $httpStatus,
        public readonly ?string $requestId,
        public readonly ?string $client,
        public readonly ?string $ip,
        public readonly ?string $userAgent,
        public readonly int|string|null $recordedActivityId,
    ) {}
}
