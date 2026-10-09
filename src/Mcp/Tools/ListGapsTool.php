<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Mcp\Tools;

use HeiHallo\McpKit\Mcp\Resources\GapsResource;
use HeiHallo\McpKit\Mcp\Tools\Concerns\OnlyForPrivileged;
use HeiHallo\McpKit\Tools\StaffTool;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * The gaps resource as a tool, for clients that do not read resources.
 * Registered with mcp-kit.resource_tools, for privileged callers.
 */
#[IsReadOnly]
class ListGapsTool extends StaffTool
{
    use OnlyForPrivileged;

    protected string $name = 'list_gaps';

    protected string $description = 'For whoever builds this app: what people reported it cannot do yet, most-wanted first, with who asked and what was decided. The same text as the gaps resource.';

    /** @var array<string, mixed> */
    protected array $inputSchema = ['type' => 'object', 'properties' => []];

    public function handle(Request $request): Response
    {
        return app(GapsResource::class)->handle($request);
    }

    public function shouldRegister(): bool
    {
        return (bool) config('mcp-kit.gaps.enabled', true) && $this->callerIsPrivileged();
    }
}
