<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Mcp\Resources;

use HeiHallo\McpKit\Audit\McpCallContext;
use HeiHallo\McpKit\Contracts\GroundRules;
use HeiHallo\McpKit\Contracts\PrincipalResolver;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Resource;

class GroundRulesResource extends Resource
{
    protected string $name = 'ground_rules';

    protected string $mimeType = 'text/markdown';

    public function uri(): string
    {
        return config('mcp-kit.scheme', 'app').'://ground-rules';
    }

    public function description(): string
    {
        return (string) (config('mcp-kit.ground_rules.description') ?? 'House rules for working in '.config('app.name', 'this app').' through the tools — read before changing anything.');
    }

    public function handle(Request $request): Response
    {
        $tokenable = $request->user();
        $principal = $tokenable ? app(PrincipalResolver::class)->resolve($tokenable) : null;

        return Response::text(app(GroundRules::class)->text($principal, app(McpCallContext::class)->server()));
    }
}
