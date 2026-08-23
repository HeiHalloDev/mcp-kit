<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Tests\Fixtures\Mcp\Tools;

use HeiHallo\McpKit\Tools\StaffTool;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ReportTool extends StaffTool
{
    protected string $name = 'monthly_numbers';

    protected string $description = 'Aggregate numbers for a month.';

    protected array $inputSchema = ['type' => 'object', 'properties' => ['month' => ['type' => 'string']]];

    public function handle(Request $request): Response
    {
        if ($denied = $this->requireAbility($request, 'reports:read')) {
            return $denied;
        }

        return Response::json(['month' => $request->get('month', 'this'), 'total' => 42]);
    }
}
