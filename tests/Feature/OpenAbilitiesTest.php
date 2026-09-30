<?php

declare(strict_types=1);

use HeiHallo\McpKit\Exceptions\TokenRefused;
use HeiHallo\McpKit\Testing\Mcp;
use HeiHallo\McpKit\Tokens\TokenMinter;

/*
 * One server for the back office and for customers: a staff server may open
 * named abilities to people who are not staff (open_abilities), and show
 * each caller only the tools they can use (list_granted_only). Everything
 * not opened stays staff-only, at the door, in every call and at minting.
 */

function openAcme(array $open = ['acme:events:write'], bool $listGrantedOnly = true): void
{
    config()->set('mcp-kit.servers.acme.open_abilities', $open);
    config()->set('mcp-kit.servers.acme.list_granted_only', $listGrantedOnly);
}

test('without open abilities a non-staff token is still turned away at the door', function () {
    $customer = acmeUser([], role: null);

    Mcp::listTools(acmeToken($customer, ['acme:events:write']), '/mcp/acme')->assertForbidden();
});

test('an opened ability lets a non-staff person in, and only to that tool', function () {
    openAcme();
    $customer = acmeUser([], role: null);
    $token = acmeToken($customer, ['acme:events:write']);

    $names = Mcp::listTools($token, '/mcp/acme')->assertOk()->json('result.tools.*.name');

    expect($names)->toContain('log_event')
        ->not->toContain('list_things')
        ->not->toContain('update_thing');
});

test('a tool left off the list is still answered with the refusal, not "not found"', function () {
    openAcme(['acme:events:write', 'acme:things:read']);
    $customer = acmeUser(['things'], role: null);
    $token = acmeToken($customer, ['acme:events:write', 'acme:things:read']);

    // Opened and held: listed and usable.
    expect(Mcp::listTools($token, '/mcp/acme')->json('result.tools.*.name'))->toContain('list_things');

    config()->set('mcp-kit.servers.acme.open_abilities', ['acme:events:write']);

    Mcp::call($token, '/mcp/acme', 'list_things')
        ->assertOk()
        ->assertSee('no longer staff');
});

test('staff still see and use every tool their token holds', function () {
    openAcme();
    $token = acmeToken(acmeUser(), ['acme:things:read', 'acme:things:write', 'acme:events:write']);

    expect(Mcp::listTools($token, '/mcp/acme')->json('result.tools.*.name'))
        ->toContain('list_things', 'update_thing', 'log_event')
        ->not->toContain('admin_only');
});

test('a non-staff person cannot be minted a staff ability on a server with open abilities', function () {
    openAcme();
    $customer = acmeUser(['things'], role: null);

    expect(fn () => app(TokenMinter::class)->mint($customer, 'laptop', ['acme:things:read']))
        ->toThrow(TokenRefused::class, 'is for staff');

    expect(app(TokenMinter::class)->mint($customer, 'laptop', ['acme:events:write'])->accessToken->abilities)
        ->toBe(['acme:events:write']);
});

test('without list_granted_only the list is unchanged', function () {
    openAcme(listGrantedOnly: false);
    $customer = acmeUser([], role: null);

    expect(Mcp::listTools(acmeToken($customer, ['acme:events:write']), '/mcp/acme')->json('result.tools.*.name'))
        ->toContain('list_things', 'log_event');
});
