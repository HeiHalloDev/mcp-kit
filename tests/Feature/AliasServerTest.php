<?php

declare(strict_types=1);

use HeiHallo\McpKit\Contracts\AbilityCatalogue;
use HeiHallo\McpKit\Contracts\PresetResolver;
use HeiHallo\McpKit\Docs\ToolReference;
use HeiHallo\McpKit\Servers\ServerRegistry;
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

test('an alias listed before its target still boots with the target\'s settings', function () {
    $servers = config('mcp-kit.servers');
    config()->set('mcp-kit.servers', ['legacy' => $servers['legacy'], ...array_diff_key($servers, ['legacy' => true])]);

    $definition = app(ServerRegistry::class)->forClass($servers['acme']['class']);

    expect($definition->key)->toBe('acme');
});

test('a server sends its avatar in serverInfo, and none when there is none', function () {
    $token = acmeToken(acmeUser(['staff', 'things']), ['acme:things:read']);

    expect(Mcp::rpc($token, '/mcp/acme', 'initialize', ['protocolVersion' => '2025-11-25', 'capabilities' => (object) [], 'clientInfo' => ['name' => 't', 'version' => '1']])
        ->json('result.serverInfo.icons'))->toBeNull();

    config()->set('mcp-kit.servers.acme.avatar', 'https://example.test/avatar.svg');

    expect(Mcp::rpc($token, '/mcp/acme', 'initialize', ['protocolVersion' => '2025-11-25', 'capabilities' => (object) [], 'clientInfo' => ['name' => 't', 'version' => '1']])
        ->json('result.serverInfo.icons'))->toBe([['src' => 'https://example.test/avatar.svg', 'mimeType' => 'image/svg+xml', 'sizes' => ['any']]]);

    // The alias path shows the same face.
    expect(Mcp::rpc($token, '/mcp/legacy', 'initialize', ['protocolVersion' => '2025-11-25', 'capabilities' => (object) [], 'clientInfo' => ['name' => 't', 'version' => '1']])
        ->json('result.serverInfo.icons.0.src'))->toBe('https://example.test/avatar.svg');
});

test('a merged-away server\'s wildcard stays in the full preset and reaches the target', function () {
    config()->set('mcp-kit.servers.legacy.wildcard', 'legacy:*');
    config()->set('mcp-kit.catalogue.abilities.legacy:read', ['Read what the old server held', 'things', 'acme']);

    $catalogue = app(AbilityCatalogue::class);

    expect($catalogue->serverFor('legacy:*'))->toBe('acme')
        ->and($catalogue->serversFor(['legacy:*']))->toBe(['acme'])
        ->and(app(PresetResolver::class)->abilitiesFor(acmeAdmin(), 'full'))->toContain('legacy:*');
});

test('the target\'s wildcard does not reach a merged-away server\'s abilities', function () {
    config()->set('mcp-kit.servers.legacy.wildcard', 'legacy:*');
    config()->set('mcp-kit.catalogue.abilities.legacy:read', ['Read what the old server held', 'things', 'acme']);

    $catalogue = app(AbilityCatalogue::class);

    expect($catalogue->wildcardsFor('legacy:read'))->toContain('legacy:*')->not->toContain('acme:*')
        ->and($catalogue->wildcardsFor('acme:things:read'))->toContain('acme:*')
        ->and($catalogue->serversFor(['legacy:*']))->toBe(['acme']);
});
