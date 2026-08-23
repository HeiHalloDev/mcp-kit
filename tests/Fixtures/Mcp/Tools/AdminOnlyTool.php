<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Tests\Fixtures\Mcp\Tools;

use HeiHallo\McpKit\Tools\StaffTool;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

class AdminOnlyTool extends StaffTool
{
    protected string $name = 'admin_only';

    protected string $description = 'Changes what other people may do. Needs the explicit acme:admin ability.';

    protected array $inputSchema = ['type' => 'object', 'properties' => ['confirm' => ['type' => 'boolean']]];

    public function handle(Request $request): Response
    {
        if ($denied = $this->requireAbility($request, 'acme:admin')) {
            return $denied;
        }

        return $this->previewOrExecute($request, ['what' => 'admin change'], fn (): array => ['done' => true], 'Admin change');
    }
}
