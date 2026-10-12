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

    expect($registry->keys())->toBe(['acme', 'reports', 'legacy'])
        ->and(array_keys($registry->canonical()))->toBe(['acme', 'reports'])
        ->and($registry->get('legacy')->effectiveKey())->toBe('acme')
        ->and($acme->effectiveKey())->toBe('acme')
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

test('the footer gives the rate limit so the assistant paces itself, and drops it with the throttle off', function () {
    config()->set('mcp-kit.routes.throttle', 90);

    expect(app(GroundRules::class)->instructions('acme', 'Authored.'))->toContain('90 calls a minute', 'not in parallel');

    config()->set('mcp-kit.routes.throttle', null);

    expect(app(GroundRules::class)->instructions('acme', 'Authored.'))->not->toContain('calls a minute');
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

    Mcp::listTools($token, '/mcp/acme')->assertSuccessful()->assertJsonPath('result.tools.0.name', fn ($name) => is_string($name));
    Mcp::listTools($token, '/mcp/acme')->assertSuccessful()->assertJsonMissingPath('error');
    Mcp::listTools($token, '/mcp/acme')->assertJsonPath('error.code', -32000);

    // Another token of the same person is not throttled by the first.
    Mcp::listTools(acmeToken(acmeUser(), ['acme:things:read']), '/mcp/acme')->assertSuccessful();
});

test('over the limit, a tool call is answered with a tool error saying how long to wait', function () {
    config()->set('mcp-kit.routes.throttle', 1);
    $token = acmeToken(acmeUser(['staff', 'things']), ['acme:things:read']);

    Mcp::listTools($token, '/mcp/acme')->assertSuccessful();

    $response = Mcp::call($token, '/mcp/acme', 'list_things')
        ->assertOk()
        ->assertHeader('Retry-After')
        ->assertJsonPath('id', 1)
        ->assertJsonPath('result.isError', true);

    expect($response->json('result.content.0.text'))
        ->toContain('1 call a minute', 'was not run', 'Wait '.$response->headers->get('Retry-After').' seconds');
});

test('over the limit, other requests get a JSON-RPC error and a batch gets one answer per request', function () {
    config()->set('mcp-kit.routes.throttle', 1);
    $token = acmeToken(acmeUser(['staff', 'things']), ['acme:things:read']);
    $headers = ['Accept' => 'application/json, text/event-stream', 'Authorization' => "Bearer {$token}"];

    Mcp::listTools($token, '/mcp/acme')->assertSuccessful();

    Mcp::rpc($token, '/mcp/acme', 'tools/list')
        ->assertOk()
        ->assertJsonPath('error.code', -32000)
        ->assertJsonPath('error.data.retry_after', fn ($seconds) => $seconds >= 1);

    app('auth')->forgetGuards();
    $this->postJson('/mcp/acme', [
        ['jsonrpc' => '2.0', 'id' => 7, 'method' => 'tools/call', 'params' => ['name' => 'list_things', 'arguments' => (object) []]],
        ['jsonrpc' => '2.0', 'method' => 'notifications/initialized'],
        ['jsonrpc' => '2.0', 'id' => 8, 'method' => 'tools/list'],
    ], $headers)
        ->assertOk()
        ->assertJsonCount(2)
        ->assertJsonPath('0.id', 7)
        ->assertJsonPath('0.result.isError', true)
        ->assertJsonPath('1.id', 8)
        ->assertJsonPath('1.error.code', -32000);
});

test('over the limit, a notification alone still gets the 429', function () {
    config()->set('mcp-kit.routes.throttle', 1);
    $token = acmeToken(acmeUser(['staff', 'things']), ['acme:things:read']);

    Mcp::listTools($token, '/mcp/acme')->assertSuccessful();

    app('auth')->forgetGuards();
    $this->postJson('/mcp/acme', ['jsonrpc' => '2.0', 'method' => 'notifications/initialized'], [
        'Accept' => 'application/json, text/event-stream',
        'Authorization' => "Bearer {$token}",
    ])->assertStatus(429);
});
