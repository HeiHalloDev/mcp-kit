<?php

declare(strict_types=1);

use HeiHallo\McpKit\Models\ServiceClient;
use HeiHallo\McpKit\Testing\Mcp;

test('mcp:client-token creates a client, mints reads by default and refuses writes outside the allow-list', function () {
    $this->artisan('mcp:client-token', ['client' => 'Flex', '--create' => true, '--description' => 'The flex app'])
        ->expectsOutputToContain('Created service client Flex')
        ->expectsOutputToContain('Abilities: acme:things:read, reports:read')
        ->assertSuccessful();

    $client = ServiceClient::query()->where('slug', 'flex')->sole();

    expect($client->description)->toBe('The flex app')->and($client->tokens()->count())->toBe(1);

    $this->artisan('mcp:client-token', ['client' => 'flex', '--abilities' => 'acme:things:read,acme:events:write'])->assertSuccessful();

    $this->artisan('mcp:client-token', ['client' => 'flex', '--abilities' => 'acme:things:write'])
        ->expectsOutputToContain('not in mcp-kit.catalogue.service_client_writes')
        ->assertFailed();

    $this->artisan('mcp:client-token', ['client' => 'nope'])->expectsOutputToContain('Add --create')->assertFailed();
});

test('deactivating a client stops its tokens at once; revoke removes them', function () {
    $client = ServiceClient::query()->create(['name' => 'Flex', 'slug' => 'flex']);
    $token = Mcp::token($client, ['acme:things:read'], 'service');

    Mcp::listTools($token, '/mcp/acme')->assertSuccessful();

    $this->artisan('mcp:client-token', ['client' => 'flex', '--deactivate' => true])->assertSuccessful();

    Mcp::listTools($token, '/mcp/acme')->assertForbidden();

    $this->artisan('mcp:client-token', ['client' => 'flex', '--activate' => true])->assertSuccessful();
    $this->artisan('mcp:client-token', ['client' => 'flex', '--revoke' => true])->expectsOutputToContain('Revoked 1')->assertSuccessful();

    expect($client->tokens()->count())->toBe(0);
});

test('service clients can be disabled entirely', function () {
    config()->set('mcp-kit.models.service_client', null);

    $this->artisan('mcp:client-token', ['client' => 'flex'])->expectsOutputToContain('disabled')->assertFailed();
});
