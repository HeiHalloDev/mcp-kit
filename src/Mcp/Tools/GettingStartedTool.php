<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Mcp\Tools;

use HeiHallo\McpKit\Mcp\Prompts\GettingStartedPrompt;
use HeiHallo\McpKit\Tools\StaffTool;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * The getting_started prompt as a tool, for clients that do not offer
 * prompts. Registered with mcp-kit.resource_tools when the prompt is on.
 */
#[IsReadOnly]
class GettingStartedTool extends StaffTool
{
    protected string $name = 'getting_started';

    protected string $description = 'A short intro: learn how this person works (only what is not already known), suggest three things to try, and save what they confirm. The same as the getting_started prompt; offer it only when whoami shows the invitation or the person asks.';

    /** @var array<string, mixed> */
    protected array $inputSchema = [
        'type' => 'object',
        'properties' => [
            'focus' => ['type' => 'string', 'description' => 'Optional: what the person wants to get done today, to steer the suggestions.'],
        ],
    ];

    public function handle(Request $request): Response
    {
        return app(GettingStartedPrompt::class)->handle($request);
    }
}
