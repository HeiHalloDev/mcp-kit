<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Mcp\Tools;

use HeiHallo\McpKit\Audit\McpCallContext;
use HeiHallo\McpKit\Contracts\GroundRules;
use HeiHallo\McpKit\Tools\StaffTool;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * The `ground-rules` resource as a tool, for clients that do not read
 * resources: claude.ai connectors used from Claude Code pass tools through
 * and drop resources. Registered with mcp-kit.resource_tools.
 */
#[IsReadOnly]
class GetGroundRulesTool extends StaffTool
{
    protected string $name = 'get_ground_rules';

    protected string $description = 'The house rules for working here through these tools. Read them before changing anything. The same text as the ground-rules resource.';

    /** @var array<string, mixed> */
    protected array $inputSchema = ['type' => 'object', 'properties' => []];

    public function handle(Request $request): Response
    {
        return Response::text(app(GroundRules::class)->text($this->principal($request), app(McpCallContext::class)->server()));
    }
}
