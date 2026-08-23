<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Tests\Fixtures\Mcp\Tools;

use HeiHallo\McpKit\Tools\StaffTool;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
class LogEventTool extends StaffTool
{
    protected string $name = 'log_event';

    protected string $description = 'System logging from another service: a write service clients may perform.';

    protected array $inputSchema = ['type' => 'object', 'properties' => ['event' => ['type' => 'string']], 'required' => ['event']];

    public function handle(Request $request): Response
    {
        if ($denied = $this->requireAbility($request, 'acme:events:write')) {
            return $denied;
        }

        return Response::json(['logged' => $request->get('event')]);
    }
}
