<?php

declare(strict_types=1);

use HeiHallo\McpKit\Testing\Mcp;
use HeiHallo\McpKit\Tests\Fixtures\Mcp\Tools\AdminOnlyTool;
use HeiHallo\McpKit\Tests\Fixtures\Mcp\Tools\ListThingsTool;
use HeiHallo\McpKit\Tools\ToolAppearances;
use Illuminate\Support\Facades\DB;

/*
 * claude.ai keeps a connector's tool list from when it connected. The me
 * text names what arrived since, so the person hears that reconnecting
 * brings it, at the start of the session where it matters.
 */

function whoamiText(string $token): string
{
    return (string) Mcp::call($token, '/mcp/acme', 'whoami', [])->assertSuccessful()->json('result.content.0.text');
}

test('the tools a server starts with are a baseline, never news', function () {
    $token = acmeToken(acmeUser(), ['acme:things:read']);

    Mcp::listTools($token, '/mcp/acme')->assertSuccessful();

    expect(DB::table(ToolAppearances::TABLE)->where('server', 'acme')->count())->toBeGreaterThan(0)
        ->and(DB::table(ToolAppearances::TABLE)->where('server', 'acme')->whereNotNull('first_seen_at')->count())->toBe(0)
        ->and(whoamiText($token))->not->toContain('New since you connected');
});

test('a tool added later is dated, and named for whoever connected before it and may use it', function () {
    $earlier = acmeToken(acmeUser(), ['acme:things:read']);
    Mcp::listTools($earlier, '/mcp/acme');

    // A deploy adds tools: one any reader may use, one for admins only.
    DB::table(ToolAppearances::TABLE)->where('server', 'acme')->whereIn('class', [ListThingsTool::class, AdminOnlyTool::class])->delete();
    app(ToolAppearances::class)->record('acme', [ListThingsTool::class, AdminOnlyTool::class]);
    DB::table(ToolAppearances::TABLE)->whereIn('class', [ListThingsTool::class, AdminOnlyTool::class])->update(['first_seen_at' => now()->addMinute()]);

    $text = whoamiText($earlier);

    expect($text)->toContain('New since you connected')
        ->toContain('`list_things`')
        ->toContain('disconnect and connect again')
        ->not->toContain('admin_only');

    $this->travel(2)->minutes();
    $later = acmeToken(acmeUser(), ['acme:things:read']);

    expect(whoamiText($later))->not->toContain('New since you connected');
});
