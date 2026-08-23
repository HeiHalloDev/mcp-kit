<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Audit;

use HeiHallo\McpKit\Contracts\AuditWriter;
use HeiHallo\McpKit\Principal;
use Laravel\Sanctum\PersonalAccessToken;

final class NullAuditWriter implements AuditWriter
{
    public function recordCall(CallRecord $record): void {}

    public function recordWrite(WriteRecord $record): void {}

    public function recordToken(string $event, PersonalAccessToken $token, ?Principal $by, array $details = []): void {}

    public function recordDenial(?Principal $principal, string $reason, array $details = []): void {}
}
