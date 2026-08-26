<?php

declare(strict_types=1);

use HeiHallo\McpKit\Docs\ToolReference;
use HeiHallo\McpKit\Testing\Mcp;

/*
 * alias_of keeps an old path alive after servers merge: the route serves the
 * target's class, and every access decision is made as if the caller had hit
 * the target's own route. Without it, a token holding specific abilities dies
 * at the old path the moment the catalogue's server column moves — which is
 * an outage for every connected client configured against that path.
 */

test('a token whose abilities reach the target server passes at the alias path', function () {
    $token = acmeToken(acmeUser(['staff', 'things']), ['acme:things:read']);

    Mcp::listTools($token, '/mcp/legacy')
        ->assertSuccessful()
        ->assertSee('list_things');
});

test('a token that only reaches another server is turned away at the alias', function () {
    $token = acmeToken(acmeAdmin(), ['reports:read']);

    Mcp::listTools($token, '/mcp/legacy')->assertForbidden();
});

test('the alias serves the same tool list as its target', function () {
    $token = acmeToken(acmeUser(['staff', 'things']), ['acme:things:read']);

    $target = Mcp::listTools($token, '/mcp/acme');
    $alias = Mcp::listTools($token, '/mcp/legacy');

    expect($alias->json('result.tools'))->toEqual($target->json('result.tools'));
});

test('an alias never appears in the inventory or the docs', function () {
    $reference = app(ToolReference::class);

    expect($reference->inventory())->not->toHaveKey('legacy')
        ->and(collect($reference->tools())->pluck('server')->unique()->values()->all())->not->toContain('legacy');
});

test('a blocked owner is rejected at the alias too', function () {
    $token = acmeToken(acmeAdmin(['blocked_at' => now()]), ['acme:*']);

    Mcp::listTools($token, '/mcp/legacy')->assertForbidden();
});
