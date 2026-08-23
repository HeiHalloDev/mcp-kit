<?php

declare(strict_types=1);

use HeiHallo\McpKit\Testing\Mcp;

test('read-only mode hides every tool that is not annotated read-only', function () {
    config()->set('mcp-kit.read_only', true);
    $token = acmeToken(acmeAdmin(), ['acme:*']);

    Mcp::listTools($token, '/mcp/acme')
        ->assertSuccessful()
        ->assertSee('list_things')
        ->assertDontSee('update_thing')
        ->assertDontSee('admin_only')
        ->assertDontSee('remember_about_me');
});

test('read-only mode keeps a whoami tool when exposed', function () {
    config()->set('mcp-kit.read_only', true);
    config()->set('mcp-kit.me.expose_as_tool', true);
    $token = acmeToken(acmeAdmin(), ['acme:*']);

    Mcp::listTools($token, '/mcp/acme')->assertSuccessful()->assertSee('whoami');
});
