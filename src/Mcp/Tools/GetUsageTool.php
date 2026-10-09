<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Mcp\Tools;

use HeiHallo\McpKit\Mcp\Resources\UsagePersonResource;
use HeiHallo\McpKit\Mcp\Tools\Concerns\OnlyForPrivileged;
use HeiHallo\McpKit\Tools\StaffTool;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * The usage resources as a tool, for clients that do not read resources.
 * Without person it is the whole record; with it, one person's.
 * Registered with mcp-kit.resource_tools when learning is on.
 */
#[IsReadOnly]
class GetUsageTool extends StaffTool
{
    use OnlyForPrivileged;

    protected string $name = 'get_usage';

    protected string $description = 'For whoever builds this app: what people came here to do lately and whether they got it. Pass person (part of a name, or a user id) for one person. The same text as the usage resources.';

    /** @var array<string, mixed> */
    protected array $inputSchema = [
        'type' => 'object',
        'properties' => [
            'person' => ['type' => 'string', 'description' => 'Optional: part of a name, or a user id.'],
        ],
    ];

    public function handle(Request $request): Response
    {
        return app(UsagePersonResource::class)->handle($request);
    }

    public function shouldRegister(): bool
    {
        return (bool) config('mcp-kit.learning.enabled', false) && $this->callerIsPrivileged();
    }
}
