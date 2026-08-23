<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Contracts;

use HeiHallo\McpKit\Audit\CallRecord;
use HeiHallo\McpKit\Audit\WriteRecord;
use HeiHallo\McpKit\Principal;
use Laravel\Sanctum\PersonalAccessToken;

interface AuditWriter
{
    public function recordCall(CallRecord $record): void;

    public function recordWrite(WriteRecord $record): void;

    /**
     * @param  'minted'|'revoked'  $event
     * @param  array<string, mixed>  $details
     */
    public function recordToken(string $event, PersonalAccessToken $token, ?Principal $by, array $details = []): void;

    /**
     * @param  array<string, mixed>  $details
     */
    public function recordDenial(?Principal $principal, string $reason, array $details = []): void;
}
