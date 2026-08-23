<?php

declare(strict_types=1);

use HeiHallo\McpKit\Events\AccessDenied;
use HeiHallo\McpKit\Http\Middleware\EnsureMcpAccess;
use HeiHallo\McpKit\Models\ServiceClient;
use HeiHallo\McpKit\Testing\Mcp;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;

test('a staff token reaches the server and sees the catalogue', function () {
    $token = acmeToken(acmeUser(['staff', 'things']), ['acme:things:read']);

    Mcp::listTools($token, '/mcp/acme')
        ->assertSuccessful()
        ->assertSee('list_things')
        ->assertSee('remember_about_me');
});

test('an unauthenticated request is rejected with 401', function () {
    Mcp::listTools(null, '/mcp/acme')->assertUnauthorized();
});

test('a token without any catalogue ability is rejected', function () {
    Event::fake([AccessDenied::class]);
    $token = acmeToken(acmeUser(), ['calendar']);

    Mcp::listTools($token, '/mcp/acme')->assertForbidden()->assertDontSee('list_things');

    Event::assertDispatched(AccessDenied::class, fn (AccessDenied $e): bool => $e->reason === 'Token carries no MCP ability.');
});

test('a token is turned away from a server its abilities do not reach', function () {
    $token = acmeToken(acmeAdmin(), ['reports:read']);

    Mcp::listTools($token, '/mcp/reports')->assertSuccessful();
    Mcp::listTools($token, '/mcp/acme')->assertForbidden();
});

test('a blocked owner is rejected even with the wildcard', function () {
    $token = acmeToken(acmeAdmin(['blocked_at' => now()]), ['acme:*']);

    Mcp::listTools($token, '/mcp/acme')->assertForbidden();
});

test('a token stops working when its owner is blocked', function () {
    $user = acmeAdmin();
    $token = acmeToken($user, ['acme:*']);

    Mcp::listTools($token, '/mcp/acme')->assertSuccessful();

    $user->update(['blocked_at' => now()]);

    Mcp::listTools($token, '/mcp/acme')->assertForbidden();
});

test('a non-staff owner is turned away from a staff server but not from the other', function () {
    $user = acmeUser(['things', 'reports']);
    $token = acmeToken($user, ['acme:things:read', 'reports:read']);

    Mcp::listTools($token, '/mcp/acme')->assertForbidden();
    Mcp::listTools($token, '/mcp/reports')->assertSuccessful();
});

test('an active service client reaches a server that accepts service clients', function () {
    $client = ServiceClient::query()->create(['name' => 'Flex', 'slug' => 'flex']);
    $token = Mcp::token($client, ['acme:things:read', 'reports:read'], 'service');

    Mcp::listTools($token, '/mcp/acme')->assertSuccessful();
    Mcp::listTools($token, '/mcp/reports')->assertForbidden();

    expect($client->fresh()->last_used_at)->not->toBeNull();
});

test('a disabled service client is rejected', function () {
    $client = ServiceClient::query()->create(['name' => 'Old', 'slug' => 'old', 'is_active' => false]);
    $token = Mcp::token($client, ['acme:things:read'], 'service');

    Mcp::listTools($token, '/mcp/acme')->assertForbidden();
});

test('every kit route carries the access guard and never session middleware', function () {
    foreach (['mcp/acme', 'mcp/reports'] as $uri) {
        $route = collect(Route::getRoutes()->getRoutes())
            ->first(fn ($route): bool => $route->uri() === $uri && in_array('POST', $route->methods(), true));

        expect($route)->not->toBeNull("{$uri} is not registered.");

        $middleware = $route->gatherMiddleware();

        expect($middleware)->not->toContain('web')
            ->and($middleware)->not->toContain(StartSession::class)
            ->and($middleware)->toContain(EnsureMcpAccess::class)
            ->and($middleware)->toContain('auth:sanctum')
            ->and($middleware)->toContain('throttle:mcp-kit')
            ->and($route->getName())->toStartWith('mcp-kit.');
    }
});

test('a session-authenticated user never reaches the server', function () {
    $user = acmeAdmin();

    $this->actingAs($user)->postJson('/mcp/acme', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])
        ->assertForbidden();
});
