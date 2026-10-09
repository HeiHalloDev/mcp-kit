<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Mcp\Tools;

use HeiHallo\McpKit\Mcp\Resources\PlaybooksResource;
use HeiHallo\McpKit\Tools\StaffTool;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * The playbooks resource as a tool, for clients that do not read
 * resources. Registered with mcp-kit.resource_tools.
 */
#[IsReadOnly]
class ListPlaybooksTool extends StaffTool
{
    protected string $name = 'list_playbooks';

    protected string $description = 'The ways of working this person saved, plus the ones colleagues shared: what each does, what it needs, and the steps. Run one with run_playbook. The same text as the playbooks resource.';

    /** @var array<string, mixed> */
    protected array $inputSchema = ['type' => 'object', 'properties' => []];

    public function handle(Request $request): Response
    {
        return app(PlaybooksResource::class)->handle($request);
    }

    public function shouldRegister(): bool
    {
        return (bool) config('mcp-kit.playbooks.enabled', true);
    }
}
