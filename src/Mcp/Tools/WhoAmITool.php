<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Mcp\Tools;

use HeiHallo\McpKit\Mcp\Resources\MeResource;
use HeiHallo\McpKit\Tools\StaffTool;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * The `me` resource as a tool, for clients that do not read resources.
 * Registered when mcp-kit.me.expose_as_tool is on.
 */
#[IsReadOnly]
class WhoAmITool extends StaffTool
{
    protected string $name = 'whoami';

    protected string $description = 'Who you are talking to and what this token may do. The same text as the me resource.';

    /** @var array<string, mixed> */
    protected array $inputSchema = ['type' => 'object', 'properties' => []];

    public function handle(Request $request): Response
    {
        $principal = $this->principal($request);

        if ($principal === null) {
            return Response::error('Authentication required.');
        }

        return Response::text(MeResource::render($principal));
    }
}
