<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Exceptions;

use RuntimeException;

final class UnguardedMcpServer extends RuntimeException
{
    public static function forRoute(string $uri, string $problem): self
    {
        return new self(
            "The MCP route [{$uri}] {$problem}. Register servers through mcp-kit.servers (or McpKit::server()), ".
            'which applies auth:sanctum, EnsureMcpAccess and AuditMcpCall. To keep it unguarded on purpose, list the URI in mcp-kit.routes.allow_unguarded.'
        );
    }
}
