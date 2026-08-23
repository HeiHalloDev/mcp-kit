<?php

declare(strict_types=1);

use HeiHallo\McpKit\McpKit;
use HeiHallo\McpKit\Testing\Mcp;

test('the throttle can be switched off', function () {
    config()->set('mcp-kit.routes.throttle', null);

    expect(McpKit::middleware())->not->toContain('throttle:mcp-kit');
});

test('extra route middleware is appended', function () {
    config()->set('mcp-kit.routes.middleware', ['custom-one']);

    $stack = McpKit::middleware();

    expect(end($stack))->toBe('custom-one');
});

test('a shared primitive can be dropped everywhere', function () {
    config()->set('mcp-kit.shared.tools', []);
    config()->set('mcp-kit.shared.prompts', []);
    $token = acmeToken(acmeUser(), ['acme:things:read']);

    Mcp::listTools($token, '/mcp/acme')->assertSuccessful()->assertDontSee('remember_about_me');
    Mcp::rpc($token, '/mcp/acme', 'prompts/list')->assertSuccessful()->assertDontSee('getting_started');
});

test('the tokens page and the memory UI are off by default', function () {
    expect(config('mcp-kit.ui.enabled'))->toBeFalse()
        ->and(config('mcp-kit.ui.tokens_page.enabled'))->toBeFalse()
        ->and(config('mcp-kit.onboarding.ui'))->toBeFalse()
        ->and(config('mcp-kit.me.expose_as_tool'))->toBeFalse();
});
