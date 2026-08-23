<?php

declare(strict_types=1);

use HeiHallo\McpKit\Contracts\GroundRules;
use HeiHallo\McpKit\Servers\ServerRegistry;
use HeiHallo\McpKit\Testing\Mcp;
use HeiHallo\McpKit\Tests\Fixtures\Mcp\Servers\AcmeServer;

test('the registry reads the configured servers with defaults filled in', function () {
    $registry = app(ServerRegistry::class);
    $acme = $registry->get('acme');
    $reports = $registry->get('reports');

    expect($registry->keys())->toBe(['acme', 'reports'])
        ->and($acme->class)->toBe(AcmeServer::class)
        ->and($acme->path)->toBe('/mcp/acme')
        ->and($acme->uri())->toBe('mcp/acme')
        ->and($acme->routeName())->toBe('mcp-kit.acme')
        ->and($acme->requiresStaff)->toBeTrue()
        ->and($acme->serviceClients)->toBeTrue()
        ->and($reports->requiresStaff)->toBeFalse()
        ->and($reports->serviceClients)->toBeFalse()
        ->and($reports->clientName)->toBe('acme-reports')
        ->and($registry->forClass(AcmeServer::class)?->key)->toBe('acme')
        ->and($registry->forRoute('mcp-kit.reports')?->key)->toBe('reports')
        ->and($registry->forRoute('mcp/acme')?->key)->toBe('acme');
});

test('the handshake carries the authored instructions plus the kit footer', function () {
    $token = acmeToken(acmeUser(), ['acme:things:read']);

    $response = Mcp::rpc($token, '/mcp/acme', 'initialize', [
        'protocolVersion' => '2025-06-18',
        'capabilities' => (object) [],
        'clientInfo' => ['name' => 'harness', 'version' => '1'],
    ])->assertSuccessful();

    $instructions = $response->json('result.instructions');

    expect($instructions)->toStartWith('Staff tools for Acme. Things and events.')
        ->toContain('acme://me')
        ->toContain('acme://ground-rules')
        ->and($response->json('result.serverInfo.name'))->toBe('Acme');
});

test('the footer can be switched off', function () {
    config()->set('mcp-kit.instructions.append_footer', false);

    expect(app(GroundRules::class)->instructions('acme', 'Authored.'))->toBe('Authored.');
});

test('the shared primitives are appended to every server and never twice', function () {
    $token = acmeToken(acmeAdmin(), ['acme:*', 'reports:*']);

    foreach (['/mcp/acme', '/mcp/reports'] as $path) {
        $tools = collect(Mcp::listTools($token, $path)->assertSuccessful()->json('result.tools'))->pluck('name');

        expect($tools->filter(fn (string $name): bool => $name === 'remember_about_me')->count())->toBe(1);

        $resources = collect(Mcp::rpc($token, $path, 'resources/list')->assertSuccessful()->json('result.resources'))->pluck('uri');

        expect($resources->all())->toContain('acme://ground-rules', 'acme://me');

        $prompts = collect(Mcp::rpc($token, $path, 'prompts/list')->assertSuccessful()->json('result.prompts'))->pluck('name');

        expect($prompts->all())->toContain('getting_started');
    }
});

test('the throttle is keyed on the token', function () {
    config()->set('mcp-kit.routes.throttle', 2);
    $token = acmeToken(acmeUser(), ['acme:things:read']);

    Mcp::listTools($token, '/mcp/acme')->assertSuccessful();
    Mcp::listTools($token, '/mcp/acme')->assertSuccessful();
    Mcp::listTools($token, '/mcp/acme')->assertStatus(429);

    // Another token of the same person is not throttled by the first.
    Mcp::listTools(acmeToken(acmeUser(), ['acme:things:read']), '/mcp/acme')->assertSuccessful();
});
