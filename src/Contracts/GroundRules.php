<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Contracts;

use HeiHallo\McpKit\Principal;

interface GroundRules
{
    /**
     * heading => markdown body, in render order.
     *
     * @return array<string, string>
     */
    public function sections(?Principal $principal, ?string $server): array;

    public function text(?Principal $principal, ?string $server): string;

    /**
     * The server instructions as advertised in the MCP handshake: the
     * authored text plus the kit's footer.
     */
    public function instructions(string $server, string $authored): string;
}
