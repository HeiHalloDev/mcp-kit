<?php

declare(strict_types=1);

use HeiHallo\McpKit\Exceptions\UnguardedMcpServer;
use HeiHallo\McpKit\Http\Middleware\AuditMcpCall;
use HeiHallo\McpKit\Http\Middleware\EnsureMcpAccess;
use HeiHallo\McpKit\McpKitServiceProvider;
use HeiHallo\McpKit\Tests\Fixtures\Mcp\Servers\AcmeServer;
use Laravel\Mcp\Facades\Mcp;

function enforce(): void
{
    $provider = new McpKitServiceProvider(app());
    (fn () => $this->enforceGuardedServers())->call($provider);
}

test('an Mcp::web route without the kit guards refuses to boot', function () {
    Mcp::web('/mcp/rogue', AcmeServer::class)->middleware('auth:sanctum');

    expect(fn () => enforce())->toThrow(UnguardedMcpServer::class, 'mcp/rogue');
});

test('an Mcp::web route with session middleware refuses to boot', function () {
    Mcp::web('/mcp/browser', AcmeServer::class)->middleware(['web', 'auth']);

    expect(fn () => enforce())->toThrow(UnguardedMcpServer::class, 'session middleware');
});

test('an allow-listed route may stay unguarded', function () {
    config()->set('mcp-kit.routes.allow_unguarded', ['mcp/public']);
    Mcp::web('/mcp/public', AcmeServer::class);

    enforce();

    expect(true)->toBeTrue();
});

test('enforcement can be staged off', function () {
    config()->set('mcp-kit.routes.enforce', false);
    Mcp::web('/mcp/rogue', AcmeServer::class);

    enforce();

    expect(true)->toBeTrue();
});

test('Mcp::staff registers a configured server with the full stack', function () {
    config()->set('mcp-kit.servers.extra', ['class' => AcmeServer::class, 'path' => '/mcp/extra', 'wildcard' => 'acme:*']);

    $route = Mcp::staff('extra');

    expect($route->getName())->toBe('mcp-kit.extra')
        ->and($route->middleware())->toContain('auth:sanctum', EnsureMcpAccess::class, AuditMcpCall::class);

    enforce();
});
