<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Tests\Fixtures\Mcp\Servers;

use HeiHallo\McpKit\Servers\StaffServer;
use HeiHallo\McpKit\Tests\Fixtures\Mcp\Tools\AdminOnlyTool;
use HeiHallo\McpKit\Tests\Fixtures\Mcp\Tools\ListThingsTool;
use HeiHallo\McpKit\Tests\Fixtures\Mcp\Tools\LogEventTool;
use HeiHallo\McpKit\Tests\Fixtures\Mcp\Tools\UpdateThingTool;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('Acme')]
#[Instructions('Staff tools for Acme. Things and events.')]
class AcmeServer extends StaffServer
{
    protected array $tools = [
        ListThingsTool::class,
        UpdateThingTool::class,
        AdminOnlyTool::class,
        LogEventTool::class,
    ];
}
