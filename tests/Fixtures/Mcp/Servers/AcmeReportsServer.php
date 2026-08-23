<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Tests\Fixtures\Mcp\Servers;

use HeiHallo\McpKit\Servers\StaffServer;
use HeiHallo\McpKit\Tests\Fixtures\Mcp\Tools\ReportTool;

class AcmeReportsServer extends StaffServer
{
    protected string $name = 'Acme Reports';

    protected string $instructions = 'Aggregate numbers only.';

    protected array $tools = [
        ReportTool::class,
    ];
}
